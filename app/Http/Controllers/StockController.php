<?php

namespace App\Http\Controllers;

use App\Models\AchatItems;
use App\Models\Achats;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\StockHistorique;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
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

    /**
     * Créer un stock initial à partir d'un achat
     */
    public function addStock(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $validate = $request->validate([
                'achat_id' => 'required|exists:achats,id',
                'categorie' => 'nullable|string|max:300',
                'quantite' => 'required|integer|min:1',
                'quantite_min' => 'required|integer|min:1',
                'prix_vente' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:300',
            ]);

            // Récupérer l'achat
            $achat = AchatItems::with('achat_id')
                ->where('id', $validate['achat_id'])
                ->whereIn('statut_items', [AchatItems::STATUT_RECU, AchatItems::STATUT_PARTIEL])
                ->first();

            if (!$achat) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat n'est pas disponible (pas payé/reçu).",
                ], 404);
            }

            // ✅ Vérifier si cet achat est déjà utilisé
            if ($achat->estUtiliseDansStock()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat est déjà utilisé dans un stock.",
                ], 400);
            }

            DB::beginTransaction();

            $stock = Stock::create([
                'achat_id' => $validate['achat_id'],
                'categorie' => $validate['categorie'] ?? null,
                'quantite' => $validate['quantite'],
                'quantite_min' => $validate['quantite_min'],
                'entre_stock' => $validate['quantite'],
                'sortie_stock' => 0,
                'prix_vente' => $validate['prix_vente'],
                'description' => $validate['description'] ?? null,
                'actif' => true,
                'created_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Le stock a été ajouté avec succès",
                'data' => $stock->load('achat', 'historiques')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la création du stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ RENOUVELER UN STOCK avec un achat existant (SIMPLE)
     */
    public function renouvelerStock(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $validated = $request->validate([
                'stock_id' => 'required|exists:stock,id',
                'achat_id' => 'required|exists:achats,id',
                'commentaire' => 'nullable|string|max:500'
            ]);

            DB::beginTransaction();

            // Récupérer le stock existant
            $stock = Stock::findOrFail($validated['stock_id']);

            // Récupérer l'achat
            $achat = Achats::where('id', $validated['achat_id'])
                ->whereIn('statut', [Achats::ACHAT_REÇU])
                ->where('active', 1)
                ->first();

            if (!$achat) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat n'est pas valide ou n'est pas payé/reçu."
                ], 404);
            }

            // ✅ Vérifier que l'achat n'est pas déjà utilisé
            if ($achat->estUtiliseDansStock()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat est déjà utilisé dans un stock. Veuillez créer un nouvel achat."
                ], 400);
            }

            // ✅ Vérifier que c'est le même article
            if ($stock->achat->nom_service !== $achat->nom_service) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "L'article de l'achat ({$achat->nom_service}) ne correspond pas au stock ({$stock->achat->nom_service})."
                ], 400);
            }

            // Renouveler le stock
            $commentaire = $validated['commentaire'] ?? 
                "Renouvellement avec achat {$achat->numero_achat} - {$achat->quantite} unités";
            
            $stock->renouvelerStock($achat, $commentaire);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stock renouvelé avec succès",
                'data' => [
                    'stock' => $stock->fresh()->load(['achat', 'historiques.achat.fournisseur']),
                    'achat_utilise' => $achat->load('fournisseur', 'photos'),
                    'resume' => [
                        'article' => $stock->achat->nom_service,
                        'quantite_avant' => $stock->quantite - $achat->quantite,
                        'quantite_ajoutee' => $achat->quantite,
                        'quantite_apres' => $stock->quantite,
                        'nouveau_statut' => $stock->statut
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors du renouvellement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Obtenir les stocks à renouveler (rupture ou faible)
     */
    public function stocksARenouveler(): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $stocks = Stock::with([
                'achat:id,nom_service,fournisseur_id',
                'achat.fournisseur:id,nom_fournisseurs'
            ])
            ->where(function ($query) {
                $query->where('quantite', '<=', Stock::STOCK_FAIBLE)
                      ->orWhere('quantite', '=', Stock::STOCK_RUPTURE);
            })
            ->where('actif', true)
            ->get();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'message' => "Stocks à renouveler récupérés avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function historiqueStock($id): JsonResponse
    {
        try {
            $stock = Stock::with([
                'achat.fournisseur',
                'achat.items',
                'achat.photos',
                'historiques' => function($query) {
                    $query->with([
                        'achat.fournisseur',  // ✅ Charger l'achat avec le fournisseur
                        'achat.items',
                        'achat.photos', 
                        'creePar:id,fullname,email'
                    ])->orderBy('created_at', 'desc');
                }
            ])->findOrFail($id);

            // Statistiques
            $totalEntrees = $stock->historiques()
                ->whereIn('type', ['creation', 'renouvellement', 'entree'])
                ->sum('quantite');

            $totalSorties = $stock->historiques()
                ->where('type', 'sortie')
                ->sum('quantite');

            $nombreRenouvellements = $stock->historiques()
                ->where('type', 'renouvellement')
                ->count();

            // ✅ CORRECTION: Filtrer les achats null
            $achatsLies = $stock->historiques()
                ->whereNotNull('achat_id')
                ->with('achat.fournisseur')
                ->get()
                ->pluck('achat')
                ->filter() // ✅ Enlever les null
                ->unique('id')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'stock' => $stock,
                    'statistiques' => [
                        'quantite_actuelle' => $stock->quantite,
                        'total_entrees' => $totalEntrees,
                        'total_sorties' => $totalSorties,
                        'nombre_renouvellements' => $nombreRenouvellements,
                        'nombre_achats' => $achatsLies->count()
                    ],
                    'achats_lies' => $achatsLies,
                    'historique' => $stock->historiques
                ],
                'message' => "Historique récupéré avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showStocks(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $query = Stock::with([
                'creePar:id,fullname,email,role',
                'achat:id',
                'achat.items:achat_id,nom_service,quantite,quantite_recu,prix_unitaire,prix_total,prix_reel,date_commande,statut_item,bon_reception,date_livraison',
                'achat.photos'
            ])
            ->select(
                'id',
                'achat_id',
                'code_produit',
                'categorie',
                'quantite',
                'quantite_min',
                'entre_stock',
                'sortie_stock',
                'prix_vente',
                'statut',
                'actif',
                'created_by',
                'created_at'
            );

            if ($request->filled('statut')) {
                switch ($request->statut) {
                    case 'disponible':
                        $query->stockDisponible();
                        break;
                    case 'faible':
                        $query->stockFaible();
                        break;
                    case 'rupture':
                        $query->rupture();
                        break;
                }
            }

            $stocks = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'message' => 'Stocks récupérés avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStock(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $validated = $request->validate([
                'categorie' => 'sometimes|nullable|string|max:300',
                'quantite_min' => 'sometimes|required|integer|min:0',
                'prix_vente' => 'sometimes|required|numeric|min:0',
                'description' => 'sometimes|nullable|string|max:300',
            ]);

            DB::beginTransaction();

            $stock = Stock::findOrFail($id);
            $stock->update($validated);
            $stock->updateStatut();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock mis à jour avec succès',
                'data' => $stock->fresh()->load('achat')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            DB::beginTransaction();
            
            $stock = Stock::findOrFail($id);
            
            if ($stock->sortie_stock > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce stock car des articles ont déjà été vendus.'
                ], 400);
            }

            $stock->historiques()->delete();
            $stock->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stock supprimé avec succès."
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statStock(): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $statStock = [
                'total_produits_stock' => Stock::where('created_by', $userId)->count(),
                'total_stock_disponible' => Stock::where('created_by', $userId)->stockDisponible()->count(),
                'total_stock_faible' => Stock::where('created_by', $userId)->stockFaible()->count(),
                'total_stock_rupture' => Stock::where('created_by', $userId)->rupture()->count(),
                'total_valeur_stock' => Stock::where('created_by', $userId)
                    ->stockDisponible()
                    ->sum(DB::raw('prix_vente * quantite')),
            ];

            return response()->json([
                'success' => true,
                'data' => $statStock,
                'message' => 'Statistiques récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}