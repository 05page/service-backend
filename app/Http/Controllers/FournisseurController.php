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
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            return false;
        }
        return $user;
    }

    public function createFournisseur(Request $request): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
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
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
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
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
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
}
