<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\Services;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServicesController extends Controller
{
    //
    public function addServices(Request $request): JsonResponse
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

            $validate = $request->validate([
                'fournisseur_id' => 'required|string',
                'nom_service' => 'required|string|max:300',
                'description' => 'sometimes|string|max:500',
                'prix_service' => 'required|string|max:500',
                'statut' => ['sometimes', Rule::in([Services::SERVICE_DISPONIBLE])],

            ]);

            DB::beginTransaction();
            $service = Services::create([
                'fournisseur_id' => $validate['fournisseur_id'],
                'nom_service' => $validate['nom_service'],
                'description' => $validate['description'],
                'prix_service' => $validate['prix_service'],
                'statut' => $validate['statut'] ?? Services::SERVICE_DISPONIBLE,
                'active' => true,
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => [
                    $service
                ],
                'message' => 'Service ajouter avec succès',
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
                'message' => 'Erreur de validation',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function showServices(): JsonResponse
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

            $services = Services::with(['addBy:id,fullname,email,role'])->select(
                'fournisseur_id',
                'nom_service',
                'description',
                'prix_service',
                'statut',
                'active',
                'created_by',
                'created_at'
            )->get();

            return response()->json([
                'success' => true,
                'data' => $services,
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

            $service = Services::with(['addBy:id,fullname,email,role'])->select(
                'fournisseur_id',
                'nom_service',
                'description',
                'prix_service',
                'statut',
                'active',
                'created_by',
                'created_at'
            )->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $service,
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

    public function updateService(Request $request, $id): JsonResponse
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
            $updateService = $request->validate([
                'fournissuer_id' => 'sometimes|required|string',
                'nom_service' => 'sometimes|required|string',
                'description' => 'sometimes|string|max:500',
                'prix_service' => 'sometimes|required|string|max:500',
                'statut' => ['sometimes', Rule::in([Services::SERVICE_DISPONIBLE])],
            ]);

            DB::beginTransaction();
            $service = Services::findOrFail($id);
            $service->update($updateService);
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
                'message' => 'Erreur lors de la récupération des données',
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

            $service = Services::findOrFail($id);
            $service->delete();
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

            $service = Services::truncate();
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
