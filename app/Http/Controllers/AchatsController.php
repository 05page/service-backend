<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BonCommande;
use App\Models\AchatItems;
use App\Models\Achats;
use App\Models\Fournisseurs;
use App\Models\Permissions;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
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
                    'message' => "Accès réfusé.Seul un employé ayant une permission peut effectuer cette tache",
                ], 403);
            }

            $validated = $request->validate([
                'fournisseur_id' => 'required|exists:fournisseurs,id',
                'statut' => 'sometimes|required',
                'description' => 'sometimes|nullable',
                'photos.*' => 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048',
                'items' => 'required|array|min:1',
                'items.*.nom_service' => 'required|string|max:500',
                'items.*.quantite' => 'required|integer|min:1',
                'items.*.prix_unitaire' => 'required|numeric|min:0',
                'items.*.date_commande' => 'required|date',
                'items.*.date_livraison' => 'sometimes|date',
            ]);

            DB::beginTransaction();

            $achat = Achats::create([
                'fournisseur_id' => $validated['fournisseur_id'],
                'statut' => $validated['statut'] ?? Achats::ACHAT_COMMANDE,
                'active' => true,
                'description' => $validated['description'] ?? null,
                'created_by' => Auth::id()
            ]);
            $pdf = Pdf::loadView('factures.bon_commande', ['achat' => $achat])->setPaper('A4', 'landscape')->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
            ]);
            $pdfPath = storage_path("app/public/bon_achat_{$achat->id}.pdf");
            $pdf->save($pdfPath);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                    $path = $photo->storeAs('achats', $filename, 'public');

                    $achat->photos()->create([
                        'path' => 'storage/' . $path
                    ]);
                }
            }
            Mail::to($achat->fournisseur->email)->queue(new BonCommande($achat, $pdfPath));
            $achat->update([
                'bon_commande' => "storage/bon_commande_{$achat->id}.pdf"
            ]);
            //Créer les items
            foreach ($validated['items'] as $itemData) {
                $items = new AchatItems([
                    'achat_id' => $achat->id,
                    'nom_service' => $itemData['nom_service'],
                    'quantite' => $itemData['quantite'],
                    'prix_unitaire' => $itemData['prix_unitaire'],
                    'date_commande' => $itemData['date_commande'],
                    'date_livraison' => $itemData['date_livraison'],
                ]);
                $items->prix_total = $items->calculePrixTotal();
                $items->save();
            }

            $achat->update([
                'depenses_total' => $item->calculePrixTotal()
            ]);
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $achat->load('photos'),
                'message' => "Achat crée avec succès et mail envoyé"
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

    public function addBonReception(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'sometimes|array',
                'items.*.id' => 'required|exists:achat_items,id',
                'items.*.bon_reception' => 'sometimes|required|file|mimes:pdf',
                'items.*.quantite_recu' => 'required|integer|min:0',
                'items.*.date_livraison' => 'required|date'
            ]);

            Db::beginTransaction();
            //Vérifions l'achat 
            $achat = Achats::find($id);
            if (!$achat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Achat introuvable'
                ], 404);
            }
            if ($achat->statut === Achats::ACHAT_ANNULE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de réceptionner un achat annulé'
                ], 400);
            }

            $itemTraites = [];
            foreach ($validated['items'] as $index => $itemData) {
                $item = AchatItems::findOrFail($itemData['id']);

                if ($item->achat_id !== $achat->id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "L'item {$item->id} n'appartient pas à cet achat"
                    ], 400);
                }

                $quantiteRecueTotale = $itemData['quantite_recu'];
                if ($quantiteRecueTotale > $item->quantite) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "La quantité reçue ({$quantiteRecueTotale}) dépasse la quantité commandée ({$item->quantite}) pour l'item '{$item->nom_service}'"
                    ], 422);
                }

                // Gérer le fichier PDF du bon de réception (si fourni)
                $bonReceptionPath = null;
                if ($request->hasFile("items.$index.bon_reception")) {
                    $file = $request->file("items.$index.bon_reception");
                    $fileName = "bon_reception_item_{$item->id}_" . time() . '_' . uniqid() . '.pdf';
                    $path = $file->storeAs("bon_receptions", $fileName, 'public');
                    $bonReceptionPath = "storage/" . $path;
                }
                // Marquer l'item comme reçu (utilise la méthode du modèle)
                $item->update([
                    'bon_reception'=> $bonReceptionPath,
                    'quantite_recu'=> $quantiteRecueTotale,
                    'date_livraison'=> now()
                ]);
                $item->prix_reel = $quantiteRecueTotale * $item->prix_unitaire;
                $item->marquerRecu();
            }
            // ✅ Mettre à jour le statut GLOBAL de l'achat en fonction des items
            $achat->updateStatutGlobal();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Bon de réception ajouté avec succès',
                'data' => $achat->load(['items'])
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
                'message' => 'Erreur lors de l\'ajout du bon de réception',
                'error' => $e->getMessage()
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

            // ✅ MODIFIÉ : Récupérer les achats payés/reçus qui ne sont PAS encore utilisés
            $achats = Achats::with(['fournisseur:id,nom_fournisseurs', 'photos'])
                ->whereIn('statut', [Achats::ACHAT_REÇU])
                ->where('active', 1)
                ->whereDoesntHave('stockHistoriques') // ✅ Utilise la nouvelle relation
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

    /**
     * ✅ NOUVEAU : Récupérer les achats déjà utilisés pour le renouvellement
     */
    public function achatsUtilises(): JsonResponse
    {
        try {
            if (!$this->verifierPermission()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé",
                ], 403);
            }

            // Récupérer les achats déjà utilisés dans des stocks
            $achats = Achats::with([
                'fournisseur:id,nom_fournisseurs',
                'stockHistoriques.stock:id,code_produit,quantite,statut'
            ])
                ->whereIn('statut', [Achats::ACHAT_REÇU])
                ->where('active', 1)
                ->whereHas('stockHistoriques')
                ->get()
                ->map(function ($achat) {
                    // Ajouter les informations des stocks liés
                    $stocks = $achat->getTousLesStocks();
                    $achat->stocks_lies = $stocks->map(function ($stock) {
                        return [
                            'id' => $stock->id,
                            'code_produit' => $stock->code_produit,
                            'quantite' => $stock->quantite,
                            'statut' => $stock->statut
                        ];
                    });
                    return $achat;
                });

            return response()->json([
                'success' => true,
                'data' => $achats,
                'message' => "Achats utilisés récupérés avec succès"
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
