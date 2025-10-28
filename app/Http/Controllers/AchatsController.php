<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Fournisseurs;
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
                'nom_service' => 'required|string|max:500',
                'quantite' => 'required|integer|min:1',
                'prix_unitaire' => 'required|numeric|min:0',
                'date_commande' => 'required|date',
                'date_livraison' => 'sometimes|required|date',
                'statut' => 'sometimes|required',
                'description' => 'sometimes|nullable',
                'photos.*' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048'
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
                'description' => $validated['description'] ?? null,
                'created_by' => Auth::id()
            ]);

            // 🔹 Enregistre d’abord l’achat pour avoir un ID
            $achat->prix_total = $achat->calculePrixTotal();
            $achat->save();

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                    $path = $photo->storeAs('achats', $filename, 'public');

                    $achat->photos()->create([
                        'path' => 'storage/' . $path
                    ]);
                }
            }
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $achat->load('photos'),
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
            $user = Auth::user();

            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas l'accès pour ajouter un achat"
                ], 403);
            }

            $query = Achats::with([
                'creePar:id,fullname,email,role',
                'fournisseur:id,nom_fournisseurs',
                'photos:id,achat_id,path'
            ])->select(
                'id',
                'fournisseur_id',
                'nom_service',
                'quantite',
                'prix_unitaire',
                'prix_total',
                'numero_achat',
                'date_commande',
                'date_livraison',
                'statut',
                'created_by',
                'created_at'
            );

            if ($request->filled('statut')) {
                switch ($request->statut) {
                    case 'commande':
                        $query->commande();
                        break;
                    case 'reçu':
                        $query->reçu();
                        break;
                    case 'paye':
                        $query->paye();
                        break;
                    case 'annule':
                        $query->annule();
                        break;
                }
            }

            $getAchats = $query->orderBy('created_at', 'desc')->get();

            // ✅ Transformer les chemins pour utiliser l'URL complète
            $getAchats->transform(function ($achat) {
                $achat->photos->transform(function ($photo) {
                    // Supprimer 'storage/' du début si présent
                    $cleanPath = str_replace('storage/', '', $photo->path);
                    // Créer l'URL complète
                    $photo->path = url('storage/' . $cleanPath);
                    return $photo;
                });
                return $achat;
            });

            return response()->json([
                'success' => true,
                'message' => "Les achats ont été récupérés avec succès",
                'data' => $getAchats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la récupération des achats",
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function achatsDisponibles(): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé",
                ], 403);
            }

            // Récupérer les achats payés/reçus qui ne sont pas liés à un stock
            $achats = Achats::with(['fournisseur:id,nom_fournisseurs'])
                ->whereIn('statut', [Achats::ACHAT_PAYE, Achats::ACHAT_REÇU])
                ->doesntHave('stock')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $achats,
                'message' => "Achats disponibles récupérés avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue lors de la récupération",
                'error' => $e->getMessage()
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
                'statut' => ['sometimes', Rule::in([
                    Achats::ACHAT_COMMANDE,
                    Achats::ACHAT_PAYE,
                    Achats::ACHAT_REÇU
                ])],
                'description' => 'sometimes|nullable',
            ]);

            DB::beginTransaction();
            $updateAchat = Achats::findOrFail($id);

            // ✅ Recalculer le prix_total si quantite ou prix_unitaire change
            if (isset($validated['quantite']) || isset($validated['prix_unitaire'])) {
                $quantite = $validated['quantite'] ?? $updateAchat->quantite;
                $prixUnitaire = $validated['prix_unitaire'] ?? $updateAchat->prix_unitaire;
                $validated['prix_total'] = $quantite * $prixUnitaire;
            }

            $updateAchat->update($validated);

            // ✅ Le stock sera mis à jour automatiquement via l'événement 'updated' du modèle

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Achat mis à jour avec succès",
                'data' => $updateAchat->fresh()
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur de validation",
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
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
                    'message' => "Accès refusé"
                ], 403);
            }

            $reçu = Achats::findOrFail($id);
            if ($reçu->isReçu()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet achat est déjà marqué comme reçu'
                ], 400);
            }

            $reçu->marqueReçu();
            return response()->json([
                'success' => true,
                'message' => 'Achat marqué comme reçu',
                'data' => $reçu
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la confirmation de l\'achat',
                'errors' => $e->getMessage()
            ], 500);
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
                    'message' => "Accès refusé"
                ], 403);
            }

            $annule = Achats::findOrFail($id);

            if ($annule->isPaye() || $annule->isReçu()) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible d'annuler un achat déjà reçu/payé"
                ], 400);
            }

            $annule->marqueAnnule();

            return response()->json([
                'success' => true,
                'message' => "Achat annulé avec succès",
                'data' => $annule
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue',
                'errors' => $e->getMessage()
            ], 500);
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

            $deleteAchat->stocks()->delete();
            if ($deleteAchat->stocks()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cet achat car il est utilisé dans le stock.'
                ], 400);
            }

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

            $userId = Auth::id(); // ✅ Récupère l'utilisateur connecté

            $statAchat = [
                'total_achats'      => Achats::where('created_by', $userId)->count(),
                'total_commande'    => Achats::where('created_by', $userId)->commande()->count(),
                'total_confirme'    => Achats::where('created_by', $userId)->reçu()->count(),
                'total_achats_recu' => Achats::where('created_by', $userId)
                    ->where('statut', Achats::ACHAT_REÇU)
                    ->count(),
                'montant_total'     => Achats::where('created_by', $userId)
                    ->whereIn('statut', [
                        Achats::ACHAT_REÇU,
                        Achats::ACHAT_PAYE
                    ])
                    ->sum('prix_total'),
            ];
            return response()->json([
                'success' => true,
                'data' => $statAchat,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur survenue lors de la récupération des statistiques',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
}
