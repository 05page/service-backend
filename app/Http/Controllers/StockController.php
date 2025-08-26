<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
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
                'service_id' => 'required|string',
                'fournisseur_id' => 'required|string',
                'quantite' => 'required|string',
                'nom_produit' => 'required|string|max:300',
            ]);

            DB::beginTransaction();
            $stock = Stock::create([
                'service_id' => $validate['service_id'],
                'fournisseur_id' => $validate['fournisseur_id'],
                'quantite' => $validate['quantite'],
                'nom_produit' => $validate['nom_produit'],
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
                'message' => 'Erreur lors de la création du stock',
                'error' => $e->getMessage()
            ], 500); // ✅ Code 500 pour erreur serveur
        }
    }

    public function showStocks(): JsonResponse
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

            $stocks = Stock::with(['creePar:id,fullname,email,role'])->select(
                'service_id',
                'fournisseur_id',
                'quantite',
                'actif',
                'created_by',
                'created_at'
            )->get();

            return response()->json([
                'success' => true,
                'data' => $stocks,
                'message' => 'selection réussie'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
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

            $stock = Stock::with(['creePar:id,fullname,email,role'])->select(
                'service_id',
                'fournisseur_id',
                'quantite',
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
                'service_id' => 'sometimes|required|string',
                'fournisseur_id' => 'sometimes|required|string',
                'quantite' => 'sometimes|required|string',
                'nom_produit' => 'sometimes|required|string|max:300'
            ]);

            DB::beginTransaction();
            $service = Stock::findOrFail($id);
            $service->update($updateStock);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Service mis à jour avec succès',
                'data' => $service
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
                'message' => 'Erreur lors de la mise à jour du service',
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
                "message" => "Service supprimer avec succès."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de ce service',
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
                "message" => "Services supprimer avec succès."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des services',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
