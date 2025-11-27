<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ActivationCodeMail;
// use App\Models\EmployeIntermediaire;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;


class EmployeIntermediaireController extends Controller
{
    /**
     * Créer un nouvel employé ou intermédiaire (ADMIN SEULEMENT)
     */
    public function createUser(Request $request): JsonResponse
    {
        try {
            // Vérifier que l'utilisateur connecté est admin
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seuls les admins peuvent créer des employés/intermédiaires.'
                ], 403);
            }

            $validateUser = $request->validate([
                'fullname' => 'required|string|max:300',
                'email' => 'required|email|unique:users,email',
                'telephone' => 'required|string|max:10',
                'adresse' => 'required|string|max:300',
                'role' => [
                    'required',
                    Rule::in([
                        User::ROLE_EMPLOYE,
                        User::ROLE_INTERMEDIAIRE
                    ])
                ],
                'taux_commission' => 'sometimes|required|int|min:1',
            ]);

            DB::beginTransaction();

            if ($validateUser['role'] === User::ROLE_INTERMEDIAIRE) {
                // ✅ CAS INTERMÉDIAIRE : Pas d'email d'activation
                $employeIntermediaire = User::create([
                    'fullname' => $validateUser['fullname'],
                    'email' => $validateUser['email'],
                    'telephone' => $validateUser['telephone'],
                    'adresse' => $validateUser['adresse'],
                    'role' => User::ROLE_INTERMEDIAIRE,
                    'taux_commission' => $validateUser['taux_commission'] ?? null,
                    'activation_code' => null,
                    'email_verified_at' => now(),
                    'active' => true,
                    'password' => null,
                    'created_by' => Auth::id(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'employe_intermediaire' => $employeIntermediaire->load('createdBy'),
                    ],
                    'message' => 'Intermédiaire ajouté avec succès.'
                ], 201);
            } else {
                // ✅ CAS EMPLOYÉ : Envoyer l'email d'activation
                $employeIntermediaire = User::create([
                    'fullname' => $validateUser['fullname'],
                    'email' => $validateUser['email'],
                    'telephone' => $validateUser['telephone'],
                    'adresse' => $validateUser['adresse'],
                    'role' => User::ROLE_EMPLOYE,
                    'taux_commission' => $validateUser['taux_commission'] ?? 0,
                    'active' => false, // ✅ Inactif jusqu'à activation
                    'password' => null,
                    'created_by' => Auth::id(),
                    // ✅ Le code d'activation sera généré automatiquement par le modèle User (boot)
                ]);

                // ✅ ENVOYER L'EMAIL D'ACTIVATION
                $this->sendActivationEmail($employeIntermediaire);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'employe_intermediaire' => $employeIntermediaire->load('createdBy'),
                        'code_activation' => $employeIntermediaire->activation_code, // Pour les tests
                    ],
                    'message' => 'Employé ajouté avec succès. Un email d\'activation lui a été envoyé.'
                ], 201);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création employé: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activer un compte employé avec le code reçu par email
     */
    public function activateAccount(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email|exists:users,email',
                'activation_code' => 'required|string',
            ]);

            $employeIntermediaire = User::where('email', $validated['email'])
                ->where('activation_code', $validated['activation_code'])
                ->first();

            if (!$employeIntermediaire) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code d\'activation invalide ou email incorrect.'
                ], 400);
            }

            if ($employeIntermediaire->activate_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte est déjà activé.'
                ], 400);
            }

            // Activer le compte (met à jour activated_at et supprime le code)
            // $employeIntermediaire->activate_code();
            $employeIntermediaire->active = true;
            // Recharger les données depuis la base pour avoir les infos à jour
            $employeIntermediaire->save();

            return response()->json([
                'success' => true,
                'user_id' => $employeIntermediaire->id, // on renvoie bien l'id
                'message' => 'Compte activé avec succès ! Vous pouvez maintenant définir un mot de passe.'
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
                'message' => 'Erreur survenue lors de l\'activation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister tous les employés/intermédiaires (ADMIN SEULEMENT)
     */
    public function showEmploye(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé.'
                ], 403);
            }

            $employesIntermediaires = User::select(
                'id',
                'fullname',
                'email',
                'telephone',
                'role',
                'taux_commission',
                'adresse',
                'active',
                'created_by',
                'created_at'
            )->whereIn('role', [User::ROLE_EMPLOYE, User::ROLE_INTERMEDIAIRE])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employesIntermediaires
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer l'email d'activation (MÉTHODE MANQUANTE AJOUTÉE)
     */
    private function sendActivationEmail(User $user)
    {
        try {
            Mail::to($user->email)->send(new ActivationCodeMail($user));

            Log::info("Email d'activation envoyé avec succès à {$user->email}");
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'envoi de l'email d'activation à {$user->email}: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateEmploye(Request $request, $id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }
            $employeUpdate = $request->validate([
                'type' => ['sometimes', 'required', 'string', 'max:300'],
                'fullname' => ['sometimes', 'required', 'string', 'max:300'],
                'email' => ['sometimes', 'required', 'email'],
                'telephone' => ['sometimes', 'required', 'string', 'max:10'],
                'adresse' => ['sometimes', 'required', 'string', 'max:300'],
                'role' => ['sometimes', 'required', Rule::in([
                    User::ROLE_EMPLOYE,
                    User::ROLE_INTERMEDIAIRE
                ])],
            ]);

            DB::beginTransaction();

            $employe = User::findOrFail($id);

            $employe->update($employeUpdate);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Personnel mis à jour avec succès mis à jour avec succès',
                'data' => $employe
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
                'message' => 'Erreur survenue lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteEmploye($id): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $employe = User::findOrFail($id);
            $employe->delete();

            return response()->json([
                'success' => true,
                "message" => "Employe supprimer avec succès."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la suppression de cet employé',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function deleteALLEmployes(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $employe = User::truncate();

            return response()->json([
                'success' => true,
                "message" => "Tous les employés ont été supprimés avec succès."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des employés',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggleUserStatus($id, Request $request)
    {
        try {
            // On vérifie que l'employé existe
            $employe = User::findOrFail($id);

            // On récupère la valeur de "active" envoyée par le frontend
            $newStatus = $request->input('active');

            // On s'assure que c'est bien un booléen
            $employe->active = filter_var($newStatus, FILTER_VALIDATE_BOOLEAN);
            $employe->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'employé mis à jour avec succès',
                'data' => $employe
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de statut',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
