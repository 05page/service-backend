<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BonCommande;
use App\Models\AchatItems;
use App\Models\Achats;
use App\Models\Fournisseurs;
use App\Models\Permissions;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

use function Symfony\Component\Clock\now;

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
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action",
                ], 403);
            }

            $validated = $request->validate([
                'fournisseur_id' => 'required|exists:fournisseurs,id',
                'statut' => 'sometimes|required|string',
                'description' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.nom_service' => 'required|string|max:500',
                'items.*.quantite' => 'required|integer|min:1',
                'items.*.prix_unitaire' => 'required|numeric|min:0',
                'items.*.date_commande' => 'required|date',
                'items.*.date_livraison' => 'nullable|date',
                // ✅ CORRECTION: Valider le tableau de photos
                'items.*.photos' => 'nullable|array|max:4',
                'items.*.photos.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            DB::beginTransaction();

            // Créer l'achat principal
            $achat = Achats::create([
                'fournisseur_id' => $validated['fournisseur_id'],
                'statut' => $validated['statut'] ?? Achats::ACHAT_COMMANDE,
                'description' => $validated['description'] ?? null,
                'depenses_total' => 0,
                'created_by' => Auth::id()
            ]);

            // Générer le PDF du bon de commande
            $pdf = Pdf::loadView('factures.bon_commande', ['achat' => $achat])
                ->setPaper('A4', 'landscape')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'Arial',
                ]);

            $pdfPath = storage_path("app/public/bon_commande_{$achat->id}.pdf");
            $pdf->save($pdfPath);

            // Envoyer l'email au fournisseur
            Mail::to($achat->fournisseur->email)->queue(new BonCommande($achat, $pdfPath));

            $achat->update([
                'bon_commande' => "storage/bon_commande_{$achat->id}.pdf"
            ]);

            // ✅ Créer les items
            $totalDepenses = 0;

            foreach ($validated['items'] as $index => $itemData) {
                // Créer l'item
                $item = $achat->items()->create([
                    'nom_service' => $itemData['nom_service'],
                    'quantite' => $itemData['quantite'],
                    'prix_unitaire' => $itemData['prix_unitaire'],
                    'quantite_recu' => 0,
                    'prix_reel' => 0,
                    'date_commande' => $itemData['date_commande'],
                    'date_livraison' => $itemData['date_livraison'] ?? null,
                    'statut_item' => AchatItems::STATUT_EN_ATTENTE,
                ]);

                // ✅ CORRECTION: Calculer et sauvegarder le prix total
                $item->prix_total = $item->calculePrixTotal();
                $item->save();

                $totalDepenses += $item->prix_total;

                // ✅ CORRECTION: Gérer les photos de l'item
                if ($request->hasFile("items.{$index}.photos")) {
                    foreach ($request->file("items.{$index}.photos") as $photo) {
                        $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                        $path = $photo->storeAs("achats/items/{$item->id}", $filename, 'public');

                        $item->photos()->create([
                            'path' => 'storage/' . $path
                        ]);
                    }
                }
            }

            // Mettre à jour le total des dépenses
            $achat->update(['depenses_total' => $totalDepenses]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $achat->load(['items.photos', 'fournisseur']),
                'message' => 'Achat créé avec succès et email envoyé'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur création achat:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
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
                'photos:id,achat_id,path',
                'items:id,achat_id,nom_service,quantite,quantite_recu,prix_unitaire,prix_total,prix_reel,date_commande,statut_item,bon_reception,date_livraison'
            ])->select(
                'id',
                'fournisseur_id',
                'numero_achat',
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
            // ✅ UTILISATION DU SCOPE
            $itemsDisponibles = AchatItems::Disponible()
                ->with([
                    'achat:id,numero_achat,fournisseur_id',
                    'achat.fournisseur:id,nom_fournisseurs'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $itemsDisponibles,
                'message' => "Items disponibles récupérés avec succès"
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur survenue",
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
                    Achats::ACHAT_REÇU
                ])],
                'description' => 'sometimes|nullable',
                'photos.*' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
                'photos_to_delete' => 'sometimes|json', // IDs des photos à supprimer
            ]);

            DB::beginTransaction();

            $achat = Achats::with('photos')->findOrFail($id);

            // Gérer la suppression des photos
            if ($request->has('photos_to_delete')) {
                $photosToDelete = json_decode($request->photos_to_delete, true);

                if (is_array($photosToDelete) && !empty($photosToDelete)) {
                    foreach ($photosToDelete as $photoId) {
                        $photo = $achat->photos()->find($photoId);

                        if ($photo) {
                            // Supprimer le fichier physique
                            $filePath = str_replace('storage/', '', $photo->path);
                            if (Storage::disk('public')->exists($filePath)) {
                                Storage::disk('public')->delete($filePath);
                            }

                            // Supprimer l'enregistrement de la base de données
                            $photo->delete();
                        }
                    }
                }
            }

            // Vérifier le nombre total de photos après suppression
            $currentPhotosCount = $achat->photos()->count();
            $newPhotosCount = $request->hasFile('photos') ? count($request->file('photos')) : 0;

            if (($currentPhotosCount + $newPhotosCount) > 4) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez avoir que 4 photos maximum',
                ], 422);
            }

            // Recalcul du prix_total si changement de quantité ou de prix
            if (isset($validated['quantite']) || isset($validated['prix_unitaire'])) {
                $quantite = $validated['quantite'] ?? $achat->quantite;
                $prixUnitaire = $validated['prix_unitaire'] ?? $achat->prix_unitaire;
                $validated['prix_total'] = $quantite * $prixUnitaire;
            }

            // Retirer photos_to_delete avant l'update
            unset($validated['photos_to_delete']);

            // Mettre à jour les champs simples
            $achat->update($validated);

            // Ajouter les nouvelles photos
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
                'message' => "Achat mis à jour avec succès",
                'data' => $achat->fresh('photos')
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
                        Achats::ACHAT_REÇU
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
