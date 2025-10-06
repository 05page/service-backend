<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use App\Models\Ventes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VentesController extends Controller
{
    //

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
                    'message' => 'Accès refusé. Vous n\'avez pas la permission pour cette action.'
                ], 403);
            }

            $validated = $request->validate([
                'stock_id' => 'required|exists:stock,id',
                'nom_client' => 'required|string|max:300',
                'numero' => 'required|string|max:10',
                'adresse' => 'required|string|max:500',
                'quantite' => 'required|integer|min:1',
                'prix_total' => 'nullable|numeric|min:0',
                'statut' => ['sometimes', Rule::in([
                    Ventes::STATUT_EN_ATTENTE,
                    Ventes::STATUT_PAYE,
                    Ventes::STATUT_ANNULE
                ])],
            ]);

            // ✅ CORRECTION : Vérifier si le stock existe ET est disponible

            $stock = Stock::find($validated['stock_id']);
            if (!$stock) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock non trouvé.'
                ], 404);
            }

            // ✅ Vérification de la quantité disponible
            if ($stock->quantite < $validated['quantite']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuffisant pour cette quantité.'
                ], 400);
            }

            DB::beginTransaction();

            $vente = Ventes::create([
                'stock_id' => $stock->id,
                'nom_client' => $validated['nom_client'],
                'numero' => $validated['numero'],
                'adresse' => $validated['adresse'],
                'quantite' => $validated['quantite'],
                'prix_total' => $validated['prix_total'] ?? ($stock->prix_vente * $validated['quantite']),
                'statut' => $validated['statut'] ?? Ventes::STATUT_PAYE,
                'created_by' => Auth::id(),
            ]);

            // ✅ Décrémenter la quantité en stock
            $stock->retirerStock($validated['quantite']);
            $stock->updateStatut();
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $vente->load(['creePar']),
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
                'message' => 'Erreur survenue lors de la création de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showVentes(): JsonResponse
    {
        try {
            $user = Auth::user(); // On récupère l'utilisateur complet
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Vous n\'avez pas la permission pour cette action.'
                ], 403);
            }

            $query = Ventes::with(['creePar:id,fullname,email,role', 'stock.achat:id,nom_service'])
                ->select(
                    'id',
                    'stock_id',
                    'reference',
                    'nom_client',
                    'numero',
                    'adresse',
                    'quantite',
                    'prix_total',
                    'statut',
                    'created_by',
                    'created_at'
                );
            $ventes = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data'    => $ventes,
                'message' => $user->role === User::ROLE_ADMIN
                    ? "Toutes les ventes ont été récupérées avec succès"
                    : "Vos ventes ont été récupérées avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la récupération des ventes",
                'errors'  => $e->getMessage()
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
                'stock_id' => 'sometimes|required|exists:stock,id',
                'nom_client' => 'sometimes|required|string|max:300',
                'numero' => 'sometimes|required|string|max:10',
                'adresse' => 'sometimes|required|string|max:500',
                'quantite' => 'sometimes|required|integer|min:1',
                'prix_total' => 'sometimes|nullable|numeric|min:0',
                'statut' => ['sometimes', Rule::in([
                    Ventes::STATUT_EN_ATTENTE,
                    Ventes::STATUT_PAYE,
                    Ventes::STATUT_ANNULE
                ])]
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

            if (isset($validated['quantite']) && $validated['quantite'] != $vente->quantite) {
                $ancienneQuantite = $vente->quantite;
                $nouvelleQuantite = $validated['quantite'];
                $diff = $nouvelleQuantite - $ancienneQuantite;

                $stock = $vente->getStockAssocie();
                if ($diff > 0 && $stock->quantite < $diff) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuffisant pour cette nouvelle quantité'
                    ], 400);
                }

                // Ajuster le stock selon la différence
                if ($diff > 0) {
                    $stock->retirerStock($diff);
                } elseif ($diff < 0) {
                    $stock->addStock(abs($diff));
                }
            }

            // Mettre à jour la vente (les events géreront le recalcul automatique si nécessaire)
            $vente->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $vente->load(['stock', 'creePar']),
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

    public function marquePayer($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'messag' => "Accès refusé. Vous n\'avez pas la permission pour cette action."
                ], 403);
            }

            $vente = Ventes::findOrFail($id);
            if ($vente->marquerPaye()) {
                return response()->json([
                    'success' => true,
                    'data' => $vente->load(['stock', 'creePar']),
                    'message' => 'Vente marquée comme payée avec succès'
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => "Impossible de valider une vente déjà annulée"
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage de la vente',
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
                    'messag' => "Accès refusé. Vous n\'avez pas la permission pour cette action."
                ], 403);
            }

            $vente = Ventes::findOrFail($id);
            if ($vente->annuler()) {
                return response()->json([
                    'success' => true,
                    'data' => $vente->load(['stock', 'creePar']),
                    'message' => 'Vente marquée comme payée avec succès'
                ], 201);
            }

            return response()->json([
                'success' => false,
                'message' => "Impossible d'annuler une vente déjà payé"
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors du marquage de la vente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteVente($id): JsonResponse
    {
        try {
            // Vérification des permissions ADMIN seulement
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Désolé accès réfusé"
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
                $vente->anuler();
            }

            Ventes::truncate();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Les ventes ont été supprimé avec succès"
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
            // if (!$this->verifierPermissions()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Accès refusé'
            //     ], 403);
            // }

            $userId = Auth::id(); // ✅ Récupère l'utilisateur connecté

            $myStats = [
                'total_ventes' => Ventes::where('created_by', $userId)->count(),
                'ventes_en_attente' => Ventes::where('created_by', $userId)->EnAttente()->count(),
                'ventes_paye' => Ventes::where('created_by', $userId)->Paye()->count(),
                'ventes_annule' => Ventes::where('created_by', $userId)->Annule()->count(),
                'chiffres_affaire_total' => Ventes::where('created_by', $userId)->Paye()->sum('prix_total'),
                'mes_clients' => Ventes::where('created_by', $userId)->distinct("nom_client")->count("nom_client"),
            ];

            return response()->json([
                'success' => true,
                'data' => $myStats,
                'message' => 'Vos statistiques ont été récupérées avec succès'
            ], 200); // ✅ 200 OK (201 c'est plutôt pour une création)
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
            $clients = collect();

            if ($user->role === User::ROLE_ADMIN) {
                // Tous les clients pour l'admin
                $ventes = Ventes::select(
                    'id',
                    'reference',
                    'nom_client',
                    'numero',
                    'quantite',
                    'adresse',
                    'prix_total',
                    'stock_id'
                )->where('statut', 'payé')
                    ->with(['stock'])
                    ->get();
            } else {
                // Seulement les clients de l'employé
                $ventes = Ventes::select(
                    'id',
                    'reference',
                    'nom_client',
                    'numero',
                    'quantite',
                    'adresse',
                    'prix_total',
                    'stock_id',
                )->where('statut', 'payé')
                    ->where('created_by', $user->id)
                    ->with(['stock'])
                    ->get();
            }

            $clients = $ventes->groupBy('nom_client')
                ->map(function ($ventes, $nom) {
                    return [
                        'id' => $ventes->first()->id,
                        'nom_client' => $nom,
                        'numero' => $ventes->first()->numero,
                        'adresse' => $ventes->first()->adresse,
                        'prix_total' => $ventes->sum('prix_total'),
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
            ]);
        }
    }
}
