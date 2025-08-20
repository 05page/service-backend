<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ActivationCodeMail;
use App\Models\EmployeIntermediaire;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;


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
                'type' => ['required', Rule::in([EmployeIntermediaire::TYPE_EMPLOYE, EmployeIntermediaire::TYPE_INTERMEDIAIRE])],
                'nom_complet' => 'required|string|max:300',
                'email' => 'required|email|unique:employes_intermediaires,email',
                'telephone' => 'required|string|max:10',
                'adresse' => 'required|string|max:300',
                'taux_commission' => 'nullable|numeric|min:0|max:100', // Corrigé: commission avec 2 m
            ]);

            DB::beginTransaction();

            // Créer l'employé/intermédiaire
            $employeIntermediaire = EmployeIntermediaire::create([
                'type' => $validateUser['type'],
                'nom_complet' => $validateUser['nom_complet'],
                'email' => $validateUser['email'],
                'telephone' => $validateUser['telephone'],
                'adresse' => $validateUser['adresse'],
                'permissions' => [], // Vide au départ
                'taux_commission' => $validateUser['taux_commission'] ?? null, // Corrigé
                'created_by' => Auth::id(),
            ]);

            // Envoyer l'email avec le code d'activation
            $this->sendActivationEmail($employeIntermediaire);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'employe_intermediaire' => $employeIntermediaire->load('createdBy'),
                    'code_activation' => $employeIntermediaire->code_activation, // Pour les tests
                ],
                'message' => 'Employé/Intermédiaire créé avec succès. Un email d\'activation a été envoyé.'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
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

    /**
     * Activer un compte avec le code reçu par email
     */
    /**
 * Activer un compte employé/intermédiaire avec le code reçu par email
 */
public function activateAccount(Request $request): JsonResponse
{
    try {
        $validated = $request->validate([
            'email' => 'required|email',
            'code_activation' => 'required|string',
        ]);

        $employeIntermediaire = EmployeIntermediaire::where('email', $validated['email'])
            ->where('code_activation', $validated['code_activation'])
            ->first();

        if (!$employeIntermediaire) {
            return response()->json([
                'success' => false,
                'message' => 'Code d\'activation invalide ou email incorrect.'
            ], 400);
        }

        if ($employeIntermediaire->isActivated()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce compte est déjà activé.'
            ], 400);
        }

        // Activer le compte (met à jour activated_at et supprime le code)
        $employeIntermediaire->activate();

        // Recharger les données depuis la base pour avoir les infos à jour
        $employeIntermediaire->refresh();

        return response()->json([
            'success' => true,
            'data' => [
                'employe_intermediaire' => $employeIntermediaire,
            ],
            'message' => 'Compte activé avec succès ! Vous pouvez maintenant vous connecter au dashboard.'
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
            'message' => 'Erreur lors de l\'activation',
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

            $employesIntermediaires = EmployeIntermediaire::select(
                'type',
                'nom_complet',
                'email',
                'telephone',
                'adresse',
                'active',
                'created_by',
                'created_at'
            )
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employesIntermediaires
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envoyer l'email d'activation (MÉTHODE MANQUANTE AJOUTÉE)
     */
    private function sendActivationEmail(EmployeIntermediaire $employeIntermediaire)
    {
        try {
            Mail::to($employeIntermediaire->email)->send(new ActivationCodeMail($employeIntermediaire));
            
            // Log pour confirmation
            Log::info("Email d'activation envoyé avec succès à {$employeIntermediaire->email}");
            
        } catch (\Exception $e) {
            // Log l'erreur mais ne fait pas échouer la création du compte
            Log::error("Erreur lors de l'envoi de l'email d'activation à {$employeIntermediaire->email}: " . $e->getMessage());
            
            // Relancer l'exception pour que la transaction soit annulée
            throw $e;
        }
    }

    public function updateEmploye(Request $request, $id): JsonResponse {
        try{
            if(Auth::user()->role !== User::ROLE_ADMIN){
                return response()->json([
                    'success'=> false,
                    'message'=>"Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }
            $employeUpdate = $request->validate([
                'type'=> 'sometimes|required|string|max:300',
                'nom_complet'=> 'sometimes|required|string|max:300',
                'email'=> 'sometimes|required|email',
                'telephone'=> 'sometimes|required|string|max:10',
                'adresse'=> 'sometimes|required|string|max:300'
            ]);

            DB::beginTransaction();

            $employe = EmployeIntermediaire::findOrFail($id);

            $employe->update($employeUpdate);

            DB::commit();
            return response()->json([
                'success'=>true,
                'message'=> 'Compte mis à jour avec succès',
                'data'=> $employe
            ]);
        }catch(\Illuminate\Validation\ValidationException $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteEmploye($id): JsonResponse{
        try{
            if(Auth::user()->role !== User::ROLE_ADMIN){
                return response()->json([
                    'success'=> false,
                    'message'=>"Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $employe = EmployeIntermediaire::findOrFail($id);
            $employe->delete();

            return response()->json([
                'success'=>true,
                "message"=>"Employe supprimer avec succès."
            ]);
        }catch(\Illuminate\Validation\ValidationException $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de cet employé',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
    public function deleteALLEmployes(): JsonResponse{
        try{
            if(Auth::user()->role !== User::ROLE_ADMIN){
                return response()->json([
                    'success'=> false,
                    'message'=>"Accès refusé. Seul un admin peut supprimer."
                ], 403);
            }

            $employe = EmployeIntermediaire::truncate();

            return response()->json([
                'success'=>true,
                "message"=>"Tous les employés ont été supprimés avec succès."
            ]);
        }catch(\Illuminate\Validation\ValidationException $e){
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des employés',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}