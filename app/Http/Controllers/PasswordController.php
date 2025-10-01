<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordController extends Controller
{
    //
    public function forgotPassword(Request $request): JsonResponse
    {
        try {

            // Validation des données
            $request->validate([
                'email' => 'required|email|exists:users,email' // Vérifier si l'email existe dans la base de données
            ]);

            $user = User::where('email', $request->email)->first(); // Récupérer l'utilisateur par son email
            if (!$user->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte est désactivé'
                ], 403);
            }

            $status = Password::sendResetLink( // Envoyer le lien de réinitialisation
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) { // Vérifier si l'email a été envoyé avec succès
                return response()->json([
                    'success' => true,
                    'message' => 'Email de réinitialisation envoyé avec succès'

                ]);
            }
            return response()->json([ // Retourner une erreur si l'email n'a pas été envoyé
                'success' => false,
                'message' => 'Impossible d\'envoyer l\'email de réinitialisation'
            ], 500);
        } catch (\Illuminate\Validation\ValidationException $e) { // Gérer les erreurs de validation
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            // Validation
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            ]);

            $user = \App\Models\User::findOrFail($request->user_id);

            // Vérifier si un token existe pour cet utilisateur (dernier token envoyé)
            $tokenExists = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                ->where('email', $user->email)
                ->where('created_at', '>', now()->subHour())
                ->exists();

            if (!$tokenExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lien de réinitialisation expiré ou invalide',
                ], 400);
            }

            // Mettre à jour le mot de passe
            $user->password = $request->password; // hashé automatiquement
            $user->setRememberToken(\Illuminate\Support\Str::random(60));
            $user->save();

            // Supprimer le token utilisé
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la réinitialisation du mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function checkResetToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email'
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur introuvale'
                ], 404);
            }

            if (!$user->active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte est désactivé'
                ], 403);
            }


            // Vérifier si le token est valide
            $tokenExists = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', $request->token)
                ->where('created_at', '>', now()->subHours(1)) // Token valide 1h
                ->exists();

            if (!$tokenExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token invalide ou expiré'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token valide',
                'data' => [
                    'email' => $request->email,
                    'token' => $request->token
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'sucess' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer son mot de passe (utilisateur connecté)
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'current_password' => 'required',
                'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()->symbols()]
            ]);

            $user = $request->user();

            // Vérifier l'ancien mot de passe
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect'
                ], 400);
            }

            // Vérifier que le nouveau mot de passe est différent
            if (Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le nouveau mot de passe doit être différent de l\'ancien'
                ], 400);
            }

            // Mettre à jour le mot de passe
            $user->update([
                'password' => $request->password, // Sera hashé automatiquement
                'remember_token' => Str::random(60),
            ]);

            // Révoquer tous les autres tokens (sauf le token actuel)
            $currentToken = $request->user()->currentAccessToken();
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
