<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // Middleware pour s'assurer que l'utilisateur est connecté
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    // Récupérer les informations de l'utilisateur connecté
    public function userInfo(Request $request): JsonResponse
    {
        try {
            $user =  $request->user(); // Récupérer l'utilisateur connecté
            return response()->json([
                'response_code' => 200,
                'status' => 'success',
                'data' => [
                    'email_verified' => !is_null($user->email_verified_at), // Vérifier si l'email est vérifié
                ],
                'fullname' => $user->fullname,
                'email' => $user->email,
                'adresse' => $user->adresse,
                'telephone' => $user->telephone,
                'role' => $user->role,
                'message' => 'Informations utilisateur récupérées'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'response_code' => 500,
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des informations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request) {
        try{
            $user = $request->user();

            $validateData = $request->validate([
                'fullname'=> 'sometimes|required|string|max:300',
                'email'=> ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
                'adresse'=>'sometimes|required|string|max:255',
                'telephone'=> 'sometimes|required|string|max:10'
            ]);

            DB::beginTransaction();
            $emailChanged = isset($validateData['email']) && $validateData['email'] !== $user->email; // 

            $updateData = [];
            // Boucle pour mettre à jour les champs du profil
            foreach(['fullname', 'email', 'telephone', 'adresse'] as $field){
                if(isset($validateData[$field])){
                    $updateData[$field] = $validateData[$field];
                }
            }

            if($emailChanged){
                $updateData['email_verified_at'] = null;
            }

            $user->update($updateData);

            DB::commit();

            if($emailChanged){
                $user->sendEmailVerificationNotification();
                $message= 'Profil mis à jour. Un email de vérification a été envoyé à votre nouvelle adresse.';
            }else{
                $message = 'Profil mis à jour avec succès.';
            }
        return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->fresh(),
                    'email_verified' => !is_null($user->email_verified_at),
                    'email_changed' => $emailChanged
                ],
                'message' => $message
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
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
