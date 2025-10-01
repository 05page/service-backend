<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
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
    private function verifierPermission()
    {
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            /** @var User $user */
            $hasPermission = $user->permissions()->where('module', Permissions::MODULE_FOURNISSEURS)->where('active', true)->exists();
            if (!$hasPermission) {
                return false;
            }
        }
        return true;
    }

    public function createFournisseur(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé.Seul un employé ayant une permission peut effectuer cette tache",
                ], 403);
            }

            $validator = $request->validate([
                'nom_fournisseurs' => 'required|string|max:300',
                'email' => 'required|email|unique:fournisseurs,email',
                'telephone' => 'required|string|max:10',
                'description' => 'required|string|max:500',
                'adresse' => 'required|string|max:300'
            ]);
            DB::beginTransaction();

            $createur = Auth::user();
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
                'message' => 'Erreur survenue lors survenue lors d\'ajout du fournisseur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showFournisseur(): JsonResponse
    {
        try {
            $fournisseurs = Fournisseurs::with([
                'creePar:id,fullname,email'
            ])->select(
                'id',
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
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
            }

            $fournisseur = Fournisseurs::with([
                'creePar:id,fullname,email'
            ])->select(
                'id',
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
                'message' => 'Erreur survenue lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateFournisseur(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé. Seul un admin ou un employé ayant la permission peut modifier un fournisseur."
                ], 403);
            }

            $validator = $request->validate([
                'nom_fournisseurs' => 'sometimes|string|max:300',
                'email' => 'sometimes|email|unique:fournisseurs,email,' . $id,
                'telephone' => 'sometimes|string|max:10',
                'description' => 'sometimes|string|max:500',
                'adresse' => 'sometimes|string|max:300'
            ]);

            $fournisseur = Fournisseurs::findOrFail($id);
            $fournisseur->update($validator);

            return response()->json([
                'success' => true,
                'data' => $fournisseur->load('creePar'),
                'message' => "Fournisseur mis à jour avec succès"
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la mise à jour du fournisseur",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteFournisseur($id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seuls les admins peuvent supprimer un fournisseur."
                ], 403);
            }

            $fournisseur = Fournisseurs::findOrFail($id);
            $fournisseur->delete();

            return response()->json([
                'success' => true,
                'message' => "Fournisseur supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la suppression du fournisseur",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function desactiverFournisseur($id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
            }

            $desactiveFournisseur = Fournisseurs::findOrFail($id);
            $desactiveFournisseur->desactiver();
            return response()->json([
                'success' => true,
                'message' => "Fournisseur désactiver avec succès"
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la désactivation du fournisseur",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function reactiverFournisseur($id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
            }

            $desactiveFournisseur = Fournisseurs::findOrFail($id);
            $desactiveFournisseur->reactiver();
            return response()->json([
                'success' => true,
                'message' => "Fournisseur réactiver avec succès"
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Une erreur est survenue lors de la réactivation du fournisseur",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function statsFournisseurs(): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $statFournisseurs = [
                'total_fournisseurs' => Fournisseurs::count(),
                'total_fournisseurs_actifs' => Fournisseurs::Actif()->count(),
                'total_commande' => Achats::Commande()->whereMonth('created_at', now()->month)->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $statFournisseurs,
                'message' => "succès"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
