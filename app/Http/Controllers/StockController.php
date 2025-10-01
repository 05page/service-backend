<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    //
    public function addStock(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_STOCK)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $validate = $request->validate([
                'achat_id' => 'required|exists:achats,id',
                'categorie' => 'nullable|string|max:300',
                'quantite' => 'required|integer|min:1',
                'quantite_min' => 'required|integer|min:1',
                'prix_vente' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:300',
            ]);

            //On récupère
            $achat = Achats::with('fournisseur')
                ->where('id', $validate['achat_id'])
                ->whereIn('statut', ['paye', 'reçu'])
                ->doesntHave('stock')
                ->first();

            if (!$achat) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet achat n’est pas disponible (pas payé ou déjà lié à un stock).",
                ], 404);
            }

            DB::beginTransaction();
            $stock = Stock::create([
                'achat_id' => $validate['achat_id'],
                'categorie' => $validate['categorie'] ?? null,
                'quantite' => $validate['quantite'],
                'quantite_min' => $validate['quantite_min'],
                'prix_vente' => $validate['prix_vente'],
                'description' => $validate['description'] ?? null,
                'actif' => true,
                'created_by' => Auth::id()
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Le stock a été ajouté avec succès",
                'data' => [
                    $stock
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la création du stock',
                'error' => $e->getMessage()
            ], 500); // ✅ Code 500 pour erreur serveur
        }
    }

    public function showStocks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_STOCK)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }
            $query = Stock::with(['creePar:id,fullname,email,role', 'achat:id,nom_service'])
                ->select(
                    'id',
                    'achat_id',
                    'code_produit',
                    'quantite',
                    'quantite_min',
                    'prix_vente',
                    'statut',
                    'actif',
                    'created_by',
                    'created_at'
                );

            // 📌 Filtrage par statut si fourni
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
                'message' => 'Erreur survenue lors de la récupération des stocks',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_SERVICES)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $stock = Stock::with(['creePar:id,fullname,email,role', 'fournisseur:id,nom_fournisseurs'])->select(
                'fournisseur_id',
                'nom_produit',
                'quantite',
                'quantite_min',
                'statut',
                'actif',
                'created_by',
                'created_at'
            )->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $stock,
                'message' => 'selection réussie'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStock(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_SERVICES)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }
            $updateStock = $request->validate([
                'achat_id' => 'sometimes|required|exists:achats,id',
                'categorie' => 'sometimes|nullable|string|max:300',
                'quantite' => 'sometimes|required|integer|min:1',
                'quantite_min' => 'sometimes|required|integer|min:1',
                'prix_vente' => 'sometimes|required|numeric|min:0',
                'description' => 'sometimes|nullable|string|max:300',
            ]);

            DB::beginTransaction();
            $stock = Stock::findOrFail($id);

            $stock->update($updateStock);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Service mis à jour avec succès',
                'data' => $stock
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
                'message' => 'Erreur survenue lors de la mise à jour du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_SERVICES)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => 'false',
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $stock = Stock::findOrFail($id);
            $stock->delete();
            return response()->json([
                'success' => true,
                "message" => "Stock supprimé avec succès."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression de ce service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll(): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_SERVICES)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => 'false',
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $stock = Stock::query()->delete();
            return response()->json([
                'success' => true,
                "message" => "Stocks supprimés avec succès."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression des services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function desactiveStock($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_STOCK)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $stock = Stock::findOrFail($id);
            if (!$stock->isActif()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet article a déjà été désactivé"
                ], 400);
            }

            $stock->desactiver();
            return response()->json([
                'success' => true,
                'message' => "Cet article a été désactivé avec succès",
                'data' => $stock
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la désactivation de cet article",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function activeStock($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_STOCK)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => "Accès refusé. Vous n’avez pas la permission pour cette action."
                    ], 403);
                }
            }

            $stock = Stock::findOrFail($id);
            if ($stock->isActif()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet article a déjà été activé"
                ], 400);
            }

            $stock->reactiver();
            return response()->json([
                'success' => true,
                'message' => "Cet article a été réactivé avec succès",
                'data' => $stock
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la réactivation de cet article",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function statStock(): JsonResponse
    {
        try {
           $userId = Auth::id();
            $statStock = [
                'total_produits_stock' => Stock::where('created_by', $userId)->count(),
                'total_stock_disponible' => Stock::where('created_by', $userId)->StockDisponible()->count(),
                'total_stock_faible' => Stock::where('created_by', $userId)->StockFaible()->count(),
                'total_valeur_stock' => Stock::where('created_by', $userId)->StockDisponible()->sum('prix_vente'),

            ];

            return response()->json([
                'success' => true,
                'data' => $statStock,
                'message' => 'Vos statistiques ont été récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
