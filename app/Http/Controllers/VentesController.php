<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use App\Models\VenteItems;
use App\Models\Ventes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VentesController extends Controller
{
    private function verifierPermissions()
    {
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            /** @var User $user */
            $hasPermission = $user->permissions()
                ->where('module', Permissions::MODULE_VENTES)
                ->where('active', true)->exists();
            if (!$hasPermission) {
                return false;
            }
        }
        return true;
    }
    
    public function createVente(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.'
                ], 403);
            }

            $validated = $request->validate([
                'nom_client' => 'required|string|max:300',
                'numero' => 'required|string|max:10',
                'adresse' => 'required|string|max:500',
                'commissionaire' => 'nullable|exists:users,id',
                'montant_verse' => 'required|numeric|min:0',
                'statut' => ['sometimes', Rule::in([
                    Ventes::STATUT_EN_ATTENTE,
                    Ventes::STATUT_PAYE,
                    Ventes::STATUT_ANNULE
                ])],
                'items' => 'required|array|min:1',
                'items.*.stock_id' => 'required|exists:stock,id',
                'items.*.quantite' => 'required|integer|min:1',
            ]);

            DB::beginTransaction();

            // ✅ 1. Vérifier le stock pour chaque article
            $prixTotal = 0;
            $stocksToUpdate = [];

            foreach ($validated['items'] as $item) {
                $stock = Stock::find($item['stock_id']);

                if (!$stock) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock #{$item['stock_id']} non trouvé."
                    ], 404);
                }

                if ($stock->quantite < $item['quantite']) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Stock insuffisant pour {$stock->nom_produit}. Disponible: {$stock->quantite}"
                    ], 400);
                }

                $sousTotal = $stock->prix_vente * $item['quantite'];
                $prixTotal += $sousTotal;

                $stocksToUpdate[] = [
                    'stock' => $stock,
                    'quantite' => $item['quantite'],
                    'prix_unitaire' => $stock->prix_vente,
                    'sous_total' => $sousTotal
                ];
            }

            // ✅ 2. Créer la vente
            $montantVerse = $validated['montant_verse'];
            $vente = Ventes::create([
                'nom_client' => $validated['nom_client'],
                'numero' => $validated['numero'],
                'adresse' => $validated['adresse'],
                'commissionaire' => $validated['commissionaire'] ?? null,
                'prix_total' => $prixTotal,
                'montant_verse' => $montantVerse,
                'reglement_statut' => $montantVerse >= $prixTotal ? 1 : 0,
                'statut' => $validated['statut'] ?? Ventes::STATUT_PAYE,
                'created_by' => Auth::id(),
            ]);

            // ✅ 3. Créer les items et décrémenter le stock
            foreach ($stocksToUpdate as $data) {
                VenteItems::create([
                    'vente_id' => $vente->id,
                    'stock_id' => $data['stock']->id,
                    'quantite' => $data['quantite'],
                    'prix_unitaire' => $data['prix_unitaire'],
                    'sous_total' => $data['sous_total']
                ]);

                $data['stock']->retirerStock($data['quantite']);
                $data['stock']->updateStatut();
            }

            // ✅ 4. Enregistrer le paiement
            if ($montantVerse > 0) {
                Paiement::create([
                    'payable_id' => $vente->id,
                    'payable_type' => Ventes::class,
                    'montant_verse' => $montantVerse,
                    'created_by' => Auth::id()
                ]);
            }

            // ✅ 5. La commission est créée automatiquement via l'événement 'created' du modèle

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $vente->load(['items.stock', 'creePar', 'commissionnaire']),
                'message' => 'Vente créée avec succès'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateReglement(Request $request, $venteId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'montant_verse' => 'required|numeric|min:1',
            ]);

            $vente = Ventes::findOrFail($venteId);

            DB::beginTransaction();

            // ✅ Ajouter un nouveau paiement
            $paiement = Paiement::create([
                'payable_id'   => $vente->id,
                'payable_type' => Ventes::class,
                'montant_verse' => $validated['montant_verse'],
                'created_by'   => Auth::id(),
            ]);

            // ✅ Mettre à jour le montant total versé
            $nouveauMontant = $vente->montant_verse + $validated['montant_verse'];
            $vente->update([
                'montant_verse'     => $nouveauMontant,
                'reglement_statut'  => $nouveauMontant >= $vente->prix_total ? 1 : 0,
                'statut'            => $nouveauMontant >= $vente->prix_total ? Ventes::STATUT_PAYE : Ventes::STATUT_EN_ATTENTE,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Règlement ajouté avec succès.',
                'data' => [
                    'vente' => $vente->load('paiements'),
                    'paiement' => $paiement,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du règlement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function showVentes(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous n\'avez pas la permission pour cette action.'
                ], 403);
            }

            // ✅ Charger les items au lieu du stock direct
            $query = Ventes::with([
                'creePar:id,fullname,email,role',
                'items.stock.achat:id,nom_service',
                'commissionnaire:id,fullname,taux_commission'
            ])->select(
                'id',
                'reference',
                'nom_client',
                'numero',
                'adresse',
                'commissionaire',
                'prix_total',
                'montant_verse',
                'reglement_statut',
                'statut',
                'created_by',
                'created_at'
            );

            $ventes = $query->orderBy('created_at', 'desc')->get();

            // ✅ Formater les données pour le frontend
            $ventesFormatees = $ventes->map(function ($vente) {
                return [
                    'id' => $vente->id,
                    'reference' => $vente->reference,
                    'nom_client' => $vente->nom_client,
                    'numero' => $vente->numero,
                    'adresse' => $vente->adresse,
                    'prix_total' => $vente->prix_total,
                    'montant_verse' => $vente->montant_verse,
                    'reste_a_payer' => $vente->montantRestant(),
                    'reglement_statut' => $vente->reglement_statut,
                    'est_soldee' => $vente->estSoldee(),
                    'statut' => $vente->statut,
                    'created_at' => $vente->created_at->format('d/m/Y H:i'),

                    // ✅ Informations sur les articles
                    'items' => $vente->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'stock_id' => $item->stock_id,
                            'nom_produit' => $item->stock->achat->nom_service ?? 'Article',
                            'code_produit' => $item->stock->code_produit ?? 'N/A',
                            'quantite' => $item->quantite,
                            'prix_unitaire' => $item->prix_unitaire,
                            'sous_total' => $item->sous_total
                        ];
                    }),

                    // Nombre total d'articles
                    'nombre_articles' => $vente->items->count(),
                    'total_quantite' => $vente->items->sum('quantite'),

                    // Commissionnaire
                    'commissionnaire' => $vente->commissionnaire ? [
                        'id' => $vente->commissionnaire->id,
                        'nom' => $vente->commissionnaire->fullname,
                        'taux_commission' => $vente->commissionnaire->taux_commission
                    ] : null,

                    // Créateur
                    'cree_par' => [
                        'id' => $vente->creePar->id,
                        'nom' => $vente->creePar->fullname,
                        'role' => $vente->creePar->role
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $ventesFormatees,
                'message' => $user->role === User::ROLE_ADMIN
                    ? "Toutes les ventes ont été récupérées avec succès"
                    : "Vos ventes ont été récupérées avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la récupération des ventes",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous n\'avez pas la permission pour cette action.'
                ], 403);
            }

            $validated = $request->validate([
                'nom_client' => 'sometimes|required|string|max:300',
                'numero' => 'sometimes|required|string|max:10',
                'adresse' => 'sometimes|required|string|max:500',
                'commissionaire' => 'sometimes|nullable|exists:users,id',
                'statut' => ['sometimes', Rule::in([
                    Ventes::STATUT_EN_ATTENTE,
                    Ventes::STATUT_PAYE,
                    Ventes::STATUT_ANNULE
                ])],
                'items' => 'sometimes|array|min:1',
                'items.*.stock_id' => 'required_with:items|exists:stock,id',
                'items.*.quantite' => 'required_with:items|integer|min:1',
            ]);

            DB::beginTransaction();

            $vente = Ventes::findOrFail($id);
            
            if ($vente->isAnnule()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de modifier une vente annulée'
                ], 400);
            }

            // ✅ Gérer la modification des items si fournis
            if (isset($validated['items'])) {
                // Restaurer le stock des anciens items
                foreach ($vente->items as $oldItem) {
                    $stock = Stock::find($oldItem->stock_id);
                    if ($stock) {
                        $stock->addStock($oldItem->quantite);
                        $stock->updateStatut();
                    }
                }

                // Supprimer les anciens items
                $vente->items()->delete();

                // Créer les nouveaux items et recalculer le prix total
                $prixTotal = 0;
                foreach ($validated['items'] as $item) {
                    $stock = Stock::find($item['stock_id']);
                    
                    if (!$stock) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Stock #{$item['stock_id']} non trouvé."
                        ], 404);
                    }

                    if ($stock->quantite < $item['quantite']) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Stock insuffisant pour {$stock->nom_produit}. Disponible: {$stock->quantite}"
                        ], 400);
                    }

                    $sousTotal = $stock->prix_vente * $item['quantite'];
                    $prixTotal += $sousTotal;

                    VenteItems::create([
                        'vente_id' => $vente->id,
                        'stock_id' => $stock->id,
                        'quantite' => $item['quantite'],
                        'prix_unitaire' => $stock->prix_vente,
                        'sous_total' => $sousTotal
                    ]);

                    $stock->retirerStock($item['quantite']);
                    $stock->updateStatut();
                }

                $validated['prix_total'] = $prixTotal;
                
                // Vérifier si la vente est maintenant soldée
                if ($vente->montant_verse >= $prixTotal) {
                    $validated['reglement_statut'] = 1;
                    $validated['statut'] = Ventes::STATUT_PAYE;
                } else {
                    $validated['reglement_statut'] = 0;
                    $validated['statut'] = $vente->montant_verse > 0 ? Ventes::STATUT_EN_ATTENTE : $vente->statut;
                }
            }

            // ✅ Mettre à jour les informations de base
            $vente->update($validated);

            // ✅ La commission sera gérée automatiquement par l'événement 'updated' du modèle
            // Mais on peut aussi l'appeler explicitement pour être sûr
            if (isset($validated['commissionaire']) || isset($validated['prix_total'])) {
                $vente->refresh(); // Recharger pour avoir les dernières données
                $vente->gererCommission();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $vente->load(['items.stock', 'creePar', 'commissionnaire']),
                'message' => 'Vente mise à jour avec succès'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la mise à jour de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function marqueAnnuler($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n\'avez pas la permission pour cette action."
                ], 403);
            }

            $vente = Ventes::findOrFail($id);
            if ($vente->annuler()) {
                return response()->json([
                    'success' => true,
                    'data' => $vente->load(['items.stock', 'creePar']),
                    'message' => 'Vente annulée avec succès'
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => "La vente est déjà annulée"
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de l\'annulation de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteVente($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Désolé accès refusé"
                ], 403);
            }
            
            DB::beginTransaction();
            $vente = Ventes::findOrFail($id);

            if (!$vente->isAnnule()) {
                $vente->annuler();
            }

            $vente->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vente supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seul un admin peut supprimer toutes les ventes.'
                ], 403);
            }

            DB::beginTransaction();
            $ventes = Ventes::where('statut', '!=', Ventes::STATUT_ANNULE)->get();

            foreach ($ventes as $vente) {
                $vente->annuler();
            }

            Ventes::truncate();
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Les ventes ont été supprimées avec succès"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression de toutes les ventes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function myStats(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $myStats = [
                'total_ventes' => Ventes::where('created_by', $userId)->count(),
                'ventes_en_attente' => Ventes::where('created_by', $userId)->EnAttente()->count(),
                'ventes_paye' => Ventes::where('created_by', $userId)->Paye()->count(),
                'ventes_annule' => Ventes::where('created_by', $userId)->Annule()->count(),
                'chiffres_affaire_total' => Ventes::where('created_by', $userId)->Paye()->sum('montant_verse'),
                'mes_clients' => Ventes::where('created_by', $userId)->distinct("nom_client")->count("nom_client"),
            ];

            return response()->json([
                'success' => true,
                'data' => $myStats,
                'message' => 'Vos statistiques ont été récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function client(): JsonResponse
    {
        try {
            $user = Auth::user();

            $ventes = Ventes::with([
                'items.stock.achat:id,nom_service',
                'commissionnaire:id,fullname'
            ])->select(
                'id',
                'reference',
                'nom_client',
                'numero',
                'adresse',
                'commissionaire',
                'montant_verse',
            )->where('statut', 'paye');

            if ($user->role === User::ROLE_EMPLOYE) {
                $ventes->where('created_by', $user->id);
            }

            $ventes = $ventes->get();

            $clients = $ventes->groupBy('nom_client')
                ->map(function ($ventes, $nom) {
                    return [
                        'id' => $ventes->first()->id,
                        'nom_client' => $nom,
                        'numero' => $ventes->first()->numero,
                        'adresse' => $ventes->first()->adresse,
                        'montant_verse' => $ventes->sum('montant_verse'),
                        'nombre_ventes' => $ventes->count(),
                        'ventes' => $ventes
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'message' => 'Clients récupérés avec succès',
                'data' => $clients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}