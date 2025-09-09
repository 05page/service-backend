<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    //
    public function register(Request $request): JsonResponse
    {
        try {
            $validator = $request->validate([
                'fullname' => 'required|string|max:300',
                'email' => 'required|email|unique:users,email',
                'role' => ['sometimes', Rule::in([User::ROLE_ADMIN])],
                'telephone' => 'required|string|max:10',
                'adresse' => 'required|string|max:255',
                'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]
            ]);

            DB::beginTransaction();
            $user = User::create([
                'fullname' => $validator['fullname'],
                'email' => $validator['email'],
                'role' => $validator['role'] ?? User::ROLE_ADMIN,
                'telephone' => $validator['telephone'],
                'adresse' => $validator['adresse'],
                'password' => $validator['password'],
                'active' => true
            ]);

            // TEMPORAIRE : Désactiver l'envoi d'email pour les tests
            event(new Registered($user));

            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'email_verified' => false
                ],
                'message' => 'Inscription réussie. Un email de vérification a été envoyé.'
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
                'message' => 'Erreur survenue lors de l\'inscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validateLogin = $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $validateLogin['email'])->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvable avec cet email',
                ], 404);
            }
            if (!Hash::check($validateLogin['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect',
                ], 401);
            }


            if (!Auth::attempt($validateLogin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Indentifiants incorrects',
                ], 401);
            }
            $user = Auth::user();

            if (!$user->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est désactivé'
                ], 403);
            }

            /** @var User $user */
            $user->recordLogin();
            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'email_verified' => !is_null($user->email_verified_at),
                ],
                'message' => 'Connexion réussie'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) { // Gérer les erreurs de validation
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        try {
            $user = User::findOrFail($request->route('id')); // Récupérer l'utilisateur par son id

            if ($user->hasVerifiedEmail()) { // Vérifier si l'email est déjà vérifié
                return response()->json([
                    'response_code' => 200,
                    'status' => 'success',
                    'message' => 'Email déjà vérifié',
                    'email_verified' => true,
                    'user' => $user
                ]);
            }

            // Vérification de la signature du lien
            if (!$request->hasValidSignature()) {
                return response()->json([
                    'response_code' => 400,
                    'status' => 'error',
                    'message' => 'Lien de vérification invalide ou expiré'
                ], 400);
            }

            // Vérification du hash
            if (sha1($user->getEmailForVerification()) !== $request->route('hash')) { // Vérifier si le hash est valide
                return response()->json([
                    'response_code' => 400,
                    'status' => 'error',
                    'message' => 'Hash de vérification invalide'
                ], 400);
            }

            // Marquer comme vérifié
            $user->email_verified_at = now();
            $user->save(); // Enregistrer l'utilisateur

            // Déclencher l'événement de vérification
            event(new \Illuminate\Auth\Events\Verified($user));

            return response()->json([
                'response_code' => 200,
                'status' => 'success',
                'message' => 'Email vérifié avec succès !',
                'email_verified' => true,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'response_code' => 500,
                'status' => 'error',
                'message' => 'Erreur survenue lors de la vérification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function setPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            ]);

             $user = User::find($validated['user_id']);
            // Vérifier que le compte est activé mais sans mot de passe
            // if (!$user->activate_code()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Votre compte doit d\'abord être activé.'
            //     ], 400);
            // }

            if (!is_null($user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Un mot de passe existe déjà pour ce compte.'
                ], 400);
            }

            // Définir le mot de passe et marquer l'email comme vérifié
            $user->update([
                'password' => $validated['password'],
            ]);
            $user->email_verified_at = now();
            $user->activation_code = null;
            $user->activated_at = now();
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Mot de passe défini avec succès ! Vous pouvez maintenant vous connecter.',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) { // Gérer les erreurs de validation
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la connexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Déconnexion de l'utilisateur
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete(); // Supprimer le token de l'utilisateur
            return response()->json([
                'response_code' => 200,
                'status' => 'success',
                'message' => 'Déconnexion réussie'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'response_code' => 500,
                'status' => 'error',
                'message' => 'Erreur survenue lors de la déconnexion',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
