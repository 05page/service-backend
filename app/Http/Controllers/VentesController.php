<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Commission;
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

            // Vérification des permissions
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous n\'avez pas la permission pour cette action.'
                ], 403);
            }

            // Base de la requête
            $query = Ventes::with([
                'creePar:id,fullname,email,role',
                'items.stock.achat:id,nom_service',
                'commissionnaire:id,fullname,taux_commission',
                'paiements:id,payable_id,montant_verse,date_paiement'
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

            // Filtrer selon le rôle
            if ($user->role === User::ROLE_EMPLOYE) {
                $query->where('created_by', $user->id);
            }

            // Récupération des ventes
            $ventes = $query->orderBy('created_at', 'desc')->get();

            // Transformation pour le frontend
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
                    'created_at' => $vente->created_at?->format('d/m/Y H:i'),

                    // Détails des articles
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
                    'nombre_articles' => $vente->items->count(),
                    'total_quantite' => $vente->items->sum('quantite'),

                    // Paiements
                    'paiements' => $vente->paiements->map(function ($paiement) {
                        return [
                            'id' => $paiement->id,
                            'montant_verse' => $paiement->montant_verse,
                            'date_paiement' => $paiement->date_paiement
                                ? \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y H:i')
                                : null,
                        ];
                    }),

                    // Commissionnaire
                    'commissionnaire' => $vente->commissionnaire ? [
                        'id' => $vente->commissionnaire->id,
                        'nom' => $vente->commissionnaire->fullname,
                        'taux_commission' => $vente->commissionnaire->taux_commission
                    ] : null,

                    // Créateur
                    'cree_par' => $vente->creePar ? [
                        'id' => $vente->creePar->id,
                        'nom' => $vente->creePar->fullname,
                        'role' => $vente->creePar->role
                    ] : null,
                ];
            });

            // ✅ Réponse JSON
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


    public function historiquePaiement($id): JsonResponse
    {
        try {
            $query = Ventes::with([
                'paiements:id,payable_id,montant_verse,date_paiement'
            ])->select('id', 'reference');
            $vente = $query->orderBy('created_at', 'desc')->findOrFail($id);
            $venteHistorique = [
                'id' => $vente->id,
                'reference' => $vente->reference,
                'paiements' => $vente->paiements->map(function ($paiement) {
                    return [
                        'id' => $paiement->id,
                        'montant_verse' => $paiement->montant_verse,
                        'date_paiement' => $paiement->date_paiement
                            ? $paiement->date_paiement->format('d/m/Y H:i')
                            : null,
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'data' => $venteHistorique
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
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
            if($vente->estSoldee()){
                return response()->json([
                    'success'=> false,
                    'message'=> "Impossible d'annuler une vente déja soldée"
                ], 400);
            }
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

    public function myStats(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $myStats = [
                'chiffres_affaire_total' => Ventes::Paye()->where('created_by', $userId)->sum('montant_verse'),
                'total_ventes' => Ventes::Paye()->where('created_by', $userId)->count(),
                'ventes_en_attente' => Ventes::EnAttente()->where('created_by', $userId)->count(),
                'ventes_regles' => Ventes::Regle()->Where('created_by', $userId)->count(),
                'ventes_annule' => Ventes::Annule()->where('created_by', $userId)->count(),
                'total_commissions' => Commission::where('user_id', Auth::id())->where('etat_commission', 1)->count(),
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

            $query = Ventes::with([
                'items.stock.achat:id,nom_service',
                'commissionnaire:id,fullname,taux_commission',
                'paiements:id,payable_id,montant_verse,date_paiement'
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
                'created_at'
            )->where('statut', 'paye');

            if ($user->role === User::ROLE_EMPLOYE) {
                $query->where('created_by', $user->id);
            }

            $ventes = $query->get();

            // ✅ Formater chaque vente AVANT le groupBy (comme dans showVentes)
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
                    'created_at' => $vente->created_at,

                    // Items de la vente
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

                    'nombre_articles' => $vente->items->count(),
                    'total_quantite' => $vente->items->sum('quantite'),

                    // Paiements
                    'paiements' => $vente->paiements->map(function ($paiement) {
                        return [
                            'id' => $paiement->id,
                            'montant_verse' => $paiement->montant_verse,
                            'date_paiement' => $paiement->date_paiement?->format('d/m/Y H:i'),
                        ];
                    }),

                    // Commissionnaire
                    'commissionnaire' => $vente->commissionnaire ? [
                        'id' => $vente->commissionnaire->id,
                        'fullname' => $vente->commissionnaire->fullname,
                        'taux_commission' => $vente->commissionnaire->taux_commission
                    ] : null,
                ];
            });

            // ✅ Maintenant grouper les ventes formatées par client
            $clients = $ventesFormatees->groupBy('nom_client')
                ->map(function ($ventesClient, $nom) {
                    $premiereVente = $ventesClient->first();

                    return [
                        'id' => $premiereVente['id'],
                        'nom_client' => $nom,
                        'numero' => $premiereVente['numero'],
                        'adresse' => $premiereVente['adresse'],

                        // Totaux du client
                        'prix_total' => $ventesClient->sum('prix_total'),
                        'montant_verse' => $ventesClient->sum('montant_verse'),
                        'reste_a_payer' => $ventesClient->sum('reste_a_payer'),

                        // Statut global du client
                        'est_soldee' => $ventesClient->every(fn($v) => $v['est_soldee']),
                        'reglement_statut' => $ventesClient->every(fn($v) => $v['est_soldee'])
                            ? 'soldé'
                            : 'en cours',

                        // Statistiques
                        'nombre_ventes' => $ventesClient->count(),
                        'nombre_articles_total' => $ventesClient->sum('nombre_articles'),
                        'quantite_totale' => $ventesClient->sum('total_quantite'),

                        // Liste des ventes du client
                        'ventes' => $ventesClient->values()
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
