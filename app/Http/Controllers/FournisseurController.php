<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Permissions;
use App\Models\Fournisseurs;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FournisseurController extends Controller
{
    //
    private function createurActuel()
    {
        return Auth::user();
    }

    public function createFournisseur(Request $request): JsonResponse
    {
        try {

            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                 /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_FOURNISSEURS)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé. Vous n’avez pas la permission pour cette action.'
                    ], 403);
                }
            }

            $validator = $request->validate([
                'nom_fournisseurs' => 'required|string|max:300',
                'email' => 'required|email|unique:fournisseurs,email',
                'telephone' => 'required|string|max:10',
                'adresse' => 'required|string|max:300',
                'description' => 'required|string|max:500'
            ]);
            DB::beginTransaction();

            $createur = $this->createurActuel();
            if (!$createur) {
                return response()->json([
                    'success' => false,
                    "message" => "Utilisateur non  trouvé"
                ], 404);
            }

            $fournisseur = Fournisseurs::create([
                'nom_fournisseurs' => $validator['nom_fournisseurs'],
                'email' => $validator['email'],
                'telephone' => $validator['telephone'],
                'adresse' => $validator['adresse'],
                'description' => $validator['description'],
                'created_by' => $createur->id
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => $fournisseur->load(['creePar']),
                'message' => "Fournisseur ajouté avec succès"
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
                'message' => 'Erreur lors survenue lors d\'ajout du fournisseur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showFournisseur(): JsonResponse
    {
        try {

            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                 /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_FOURNISSEURS)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé. Vous n’avez pas la permission pour cette action.'
                    ], 403);
                }
            }

            $fournisseurs = Fournisseurs::with([
                'creePar:id,fullname,email'
            ])->select(
                'nom_fournisseurs',
                'email',
                'telephone',
                'adresse',
                'description',
                'actif',
                'created_by',
                'created_at'
            )->get();

            return response()->json([
                'success' => true,
                'data' => $fournisseurs
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function selectFournisseur($id): JsonResponse
    {
        try {

            $user = Auth::user();
            if ($user->role !== User::ROLE_ADMIN) {
                 /** @var User $user */
                $hasPermission = $user->permissions()->where('module', Permissions::MODULE_FOURNISSEURS)->where('active', true)->exists();
                if (!$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Accès refusé. Vous n’avez pas la permission pour cette action.'
                    ], 403);
                }
            }

            $fournisseur = Fournisseurs::with([
                'creePar:id,fullname,email'
            ])->select(
                'nom_fournisseurs',
                'email',
                'telephone',
                'adresse',
                'description',
                'actif',
                'created_by',
                'created_at'
            )->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $fournisseur
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
