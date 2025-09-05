<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Permissions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AchatsController extends Controller
{
    //

    private function verifierPermission()
    {
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            /** @var User $user */
            $hasPermission = $user->permissions()->where('module', Permissions::MODULE_ACHATS)->where('active', true)->exists();
            if (!$hasPermission) {
                return false;
            }
        }
        return true;
    }
    
    public function createAchat(Request $request)
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé.Seul un employé ayant une permission peut effectuer cette tache",
                ], 403);
            }

            $validated = $request->validate([
                'fournisseur_id' => 'required|exists:fournisseurs,id',
                'nom_service' => 'required|string|max:300',
                'quantite' => 'required|integer|min:1',
                'prix_unitaire' => 'required|numeric|min:0',
                'date_commande' => 'required|date',
                'date_livraison' => 'sometimes|required|date',
                'statut' => 'sometimes|required',
                'mode_paiement' => ['sometimes', Rule::in([
                    Achats::MODE_PAIMENT_VIREMENT,
                    Achats::MODE_PAIEMENT_ESPECES,
                    Achats::MODE_PAIEMENT_MOBILE_MONEY,
                ])],
                'description' => 'sometimes|nullable',
            ]);

            DB::beginTransaction();

            $achat = new Achats([
                'fournisseur_id' => $validated['fournisseur_id'],
                'nom_service' => $validated['nom_service'],
                'quantite' => $validated['quantite'],
                'prix_unitaire' => $validated['prix_unitaire'],
                'date_commande' => $validated['date_commande'],
                'date_livraison' => $validated['date_livraison'],
                'statut' => $validated['statut'] ?? Achats::ACHAT_COMMANDE,
                'mode_paiement' => $validated['mode_paiement'] ?? Achats::MODE_PAIEMENT_ESPECES,
                'description' => $validated['description'] ?? null,
                'created_by' => Auth::id()
            ]);

            $achat->prix_total = $achat->calculePrixTotal();

            $achat->save();
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $achat,
                'message' => "Achat crée avec succès"
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
                'message' => 'Erreur survenue lors de la création de l\'achat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function showAchats(Request $request): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas l'accès pour cette action"
                ], 403);
            }
            $query = Achats::with(['creePar:id,fullname,email,role', 'fournisseur:id,nom_fournisseurs'])->select(
                'fournisseur_id',
                'nom_service',
                'quantite',
                'prix_unitaire',
                'prix_total',
                'numero_achat',
                'date_commande',
                'date_livraison',
                'mode_paiement',
                'created_by',
                'created_at'
            );

            if ($request->filled('statut')) {
                switch ($request->statut) {
                    case 'commande':
                        $query->commande(); // scopeCommande
                        break;
                    case 'reçu':
                        $query->reçu(); // scopeReçu
                        break;
                    case 'paye':
                        $query->paye(); // scopePaye
                        break;

                    case 'annule':
                        $query->annule();
                        break;
                }
            }

            $getAchats = $query->get();

            return response()->json([
                'success' => true,
                'message' => "Les achats ont été récupéré avec succès",
                'data' => $getAchats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur est survenue lors de la récupérarion des ventes",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function achatsDisponibles(): JsonResponse
    {
        try {

        if(!$this->verifierPermission()){
            return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé.Seul un employé ayant une permission peut effectuer cette tache",
            ], 403);
        }

            // Récupérer les achats payés qui ne sont pas encore attribués à un stock
            $achats = Achats::with(['fournisseur:id,nom_fournisseurs'])
                ->where('statut', Achats::ACHAT_PAYE)
                ->doesntHave('stock') // relation stock() à définir dans le modèle Achats
                ->get();

            return response()->json([
                'success' => true,
                'data' => $achats,
                'message' => "Achats disponibles récupérés avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la récupération des achats disponibles",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function selectAchat($id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas l'accès pour cette action"
                ], 403);
            }
            $getAchat = Achats::with(['creePar:id,fullname,email,role', 'fournisseur:id,nom_fournisseurs'])->select(
                'fournisseur_id',
                'nom_service',
                'quantite',
                'prix_unitaire',
                'prix_total',
                'numero_achat',
                'date_commande',
                'date_livraison',
                'mode_paiement',
                'created_by',
                'created_at'
            )->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => "L'achat a été récupéré avec succès",
                'data' => $getAchat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la récupérarion cet achat",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function updateAchat(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action",
                ], 403);
            }

            $validated = $request->validate([
                'fournisseur_id' => 'sometimes|required|exists:fournisseurs,id',
                'nom_service' => 'sometimes|required|string|max:300',
                'quantite' => 'sometimes|required|integer|min:1',
                'prix_unitaire' => 'sometimes|required|numeric|min:0',
                'date_commande' => 'sometimes|required|date',
                'date_livraison' => 'sometimes|required|date',
                'statut' => 'sometimes|required',
                'mode_paiement' => ['sometimes', Rule::in([
                    Achats::MODE_PAIMENT_VIREMENT,
                    Achats::MODE_PAIEMENT_ESPECES
                ])],
                'description' => 'sometimes|nullable',
            ]);

            DB::beginTransaction();
            $updateAchat = Achats::findOrFail($id);

            $updateAchat->update($validated);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Achat mis à jour avec succès",
                'data' => $updateAchat
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la modification de l'achat",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function marqueReçu($id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé"
                ], 403);
            }

            $reçu = Achats::findOrFails($id);
            if ($reçu->isReçu()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet achat est déjà marqué comme confirmé'
                ], 400);
            }

            $reçu->marquePaye();
            return response()->json([
                'success' => true,
                'message' => 'Achat marqué comme reçu',
                'data' => $reçu
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur est survenu survenue lors de la confirmation de l\'achat',
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function marquePaye($id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé"
                ], 403);
            }

            $paye = Achats::findOrFail($id);
            if ($paye->isPaye()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet achat est déjà marqué comme payé'
                ], 400);
            }
            $paye->marquePaye();
            return response()->json([
                'success' => true,
                'message' => 'Achat marqué comme payé',
                'data' => $paye
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function marqueAnnule($id): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé"
                ], 403);
            }

            $annule = Achats::findOrFails($id);
            if ($annule->isPaye()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet achat est déjà marqué comme payé'
                ], 400);
            }

            $annule->marquePaye();

            return response()->json([
                'success' => false,
                'message' => "Impossible d'anunuler un achat deja reçu/payé"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
                'errors' => $e->getMessage()
            ]);
        }
    }

    public function deleteAchat($id): JsonResponse
    {
        try {
            // Vérification des permissions ADMIN seulement
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès réfusé.Seul un employé ayant une permission peut effectuer cette tache",
                ], 403);
            }

            DB::beginTransaction();
            $deleteAchat = Achats::findOrFail($id);

            $deleteAchat->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Achat supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'achat',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAll(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seul un admin peut supprimer toutes les ventes.'
                ], 403);
            }

            DB::beginTransaction();
            $deleteAllAchats = Achats::where('statut', '!=', Achats::ACHAT_ANNULE)->get();

            foreach ($deleteAllAchats as $achat) {
                $achat->anuler();
            }

            Achats::truncate();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => "Les achats ont été supprimé avec succès"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de tous les achats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function statsAchat(): JsonResponse
    {
        try {
            if (Auth::user()->role !== User::ROLE_ADMIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès refusé. Seul un admin peut supprimer toutes les ventes.'
                ], 403);
            }

            $statAchat = [
                'total_commande' => Achats::Commande()->count(),
                'total_confirme' => Achats::Reçu()->count(),
                'montant_total' => Achats::Reçu()->sum('prix_total')
            ];

            return response()->json([
                'success' => true,
                'data' => $statAchat,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'errors'=> $e->getMessage()
            ], 500);
        }
    }
}
