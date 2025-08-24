<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PermissionsController extends Controller
{
    //
    public function createPermission(Request $request)
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seuls les admins peuvent attribuer une permission"
                ], 403);
            }

            $validatePermission = $request->validate([
                'employe_id' => 'required|exists:users,id', // ✅ Ajouté
                'description' => 'required|string|max:300',
                'module' => ['required', Rule::in([Permissions::MODULE_FOURNISSEURS, Permissions::MODULE_SERVICES, Permissions::MODULE_ACHATS, Permissions::MODULE_STOCK, Permissions::MODULE_VENTES, Permissions::MODULE_FACTURES])],
            ]);

            DB::beginTransaction();

            $permissions = Permissions::create([
                'description' => $validatePermission['description'],
                'module' => $validatePermission['module'],

                'created_by' => Auth::id(),
                'employe_id' => $validatePermission['employe_id']
            ]);

            $employe = User::findOrFail($validatePermission['employe_id']); // permet de récupérer l'employé
            $modules = $employe->permissions()->pluck('module')->toArray(); // permet de récupérer les modules de l'employé
            $employe->update(['permissions' => $modules]); // permet de mettre à jour les modules de l'employé
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $permissions,
                'message' => "Permission attribuée avec succès"
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'succes' => false,
                'message' => "Erreur survenue lors de l'attribution de la permission",
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showPermission(): JsonResponse
    {
        try {

            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.'
                ], 403);
            }

            $permissions = Permissions::with([
                'employe:id,role,fullname,email',        // Seulement les colonnes nécessaires
                'createdBy:id,fullname,email'         // Seulement les colonnes nécessaires
            ])->select(
                'employe_id',
                'created_by',
                'description',
                'module',
                'created_at'
            )->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function selectPermission($id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $permission = Permissions::with([
                'employe:id,role,fullname,email',        // Seulement les colonnes nécessaires
                'createdBy:id,fullname,email'
            ])->select(
                'employe_id',
                'created_by',
                'description',
                'module',
                'created_at'
            )->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $permission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function activePermission($id)
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $permission = Permissions::findOrFail($id);
            $permission->active = !$permission->active;
            $permission->save();

            return response()->json([
                'success' => true,
                'message' => $permission->active
                    ? "Permission activée avec succès."
                    : "Permission désactivée avec succès.",
                
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'activation/désactivation de la permission",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
