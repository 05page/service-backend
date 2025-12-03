<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\BonCommande;
use App\Models\AchatItems;
use App\Models\Achats;
use App\Models\AchatPhotos;
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
                'items.*.photos' => 'nullable|array|max:4',
                'items.*.photos.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            ]);

            DB::beginTransaction();

            // 1️⃣ Créer l'achat principal
            $achat = Achats::create([
                'fournisseur_id' => $validated['fournisseur_id'],
                'statut' => $validated['statut'] ?? Achats::ACHAT_COMMANDE,
                'description' => $validated['description'] ?? null,
                'depenses_total' => 0,
                'created_by' => Auth::id()
            ]);

            \Log::info('✅ Achat créé', [
                'id' => $achat->id,
                'numero' => $achat->numero_achat
            ]);

            $totalDepenses = 0;

            // 2️⃣ Créer TOUS les items AVANT le PDF
            foreach ($validated['items'] as $index => $itemData) {
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

                $item->prix_total = $item->calculePrixTotal();
                $item->save();

                $totalDepenses += $item->prix_total;

                \Log::info('✅ Item créé', [
                    'id' => $item->id,
                    'nom_service' => $item->nom_service,
                    'quantite' => $item->quantite,
                    'prix_total' => $item->prix_total
                ]);

                // Gérer les photos de l'item
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

            // 3️⃣ Mettre à jour le total des dépenses
            $achat->update(['depenses_total' => $totalDepenses]);

            // 4️⃣ CRITIQUE: Recharger EXPLICITEMENT avec toutes les relations
            $achat->load([
                'fournisseur',
                'items',
                'creePar'
            ]);

            // 5️⃣ VÉRIFICATION AVANT PDF - Debug
            \Log::info('📄 Vérification données AVANT génération PDF:', [
                'achat_id' => $achat->id,
                'numero_achat' => $achat->numero_achat,
                'fournisseur_existe' => $achat->fournisseur ? 'OUI' : 'NON',
                'fournisseur_nom' => $achat->fournisseur->nom_fournisseurs ?? 'NULL',
                'items_count' => $achat->items->count(),
                'premier_item' => $achat->items->isNotEmpty() ? [
                    'id' => $achat->items->first()->id,
                    'nom_service' => $achat->items->first()->nom_service,
                    'quantite' => $achat->items->first()->quantite,
                    'prix_unitaire' => $achat->items->first()->prix_unitaire,
                    'prix_total' => $achat->items->first()->prix_total,
                ] : 'AUCUN ITEM',
                'creePar_existe' => $achat->creePar ? 'OUI' : 'NON',
                'creePar_nom' => $achat->creePar->fullname ?? 'NULL',
            ]);

            // 6️⃣ Vérifier qu'il y a bien des items
            if ($achat->items->isEmpty()) {
                \Log::error('ERREUR CRITIQUE: Aucun item trouvé avant génération PDF');
                throw new \Exception('Aucun item créé pour cet achat');
            }

            // 7️⃣ Générer le PDF
            try {
                \Log::info('Début génération PDF...');

                $pdf = Pdf::loadView('factures.bon_commande', [
                    'achat' => $achat
                ])
                    ->setPaper('A4', 'landscape')
                    ->setOptions([
                        'isHtml5ParserEnabled' => true,
                        'isPhpEnabled' => true,
                        'defaultFont' => 'Arial',
                    ]);

                $pdfPath = storage_path("app/public/bon_commande_{$achat->id}.pdf");
                $pdf->save($pdfPath);

                \Log::info('PDF généré avec succès', [
                    'path' => $pdfPath,
                    'size' => filesize($pdfPath) . ' bytes'
                ]);

                // Mettre à jour le chemin du bon de commande
                $achat->update([
                    'bon_commande' => "storage/bon_commande_{$achat->id}.pdf"
                ]);

                // 8️⃣ Envoyer l'email
                if ($achat->fournisseur && $achat->fournisseur->email) {
                    //pour envoyer le mail avec queue utilise cette commande en terminal:php artisan queue:work ou sinon remplace ->queue() par ->send()
                    Mail::to($achat->fournisseur->email)->queue(
                        new BonCommande($achat, $pdfPath)
                    );
                    \Log::info('Email envoyé', [
                        'to' => $achat->fournisseur->email
                    ]);
                } else {
                    \Log::warning('Email fournisseur non disponible');
                }
            } catch (\Exception $pdfError) {
                \Log::error('Erreur génération PDF:', [
                    'message' => $pdfError->getMessage(),
                    'file' => $pdfError->getFile(),
                    'line' => $pdfError->getLine(),
                    'trace' => $pdfError->getTraceAsString()
                ]);

                // Ne pas bloquer la création si le PDF échoue
                // mais avertir l'utilisateur
            }

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
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addBonReception(Request $request, $id): JsonResponse
    {
        try {
            Log::info("=== DÉBUT addBonReception ===");
            Log::info("Request data:", $request->all());

            // ✅ Validation : Les items sont optionnels, mais s'ils sont fournis, ils doivent être complets
            $validated = $request->validate([
                'items' => 'required|array|min:1', // Au moins 1 item doit être envoyé
                'items.*.id' => 'required|exists:achat_items,id',
                'items.*.quantite_recu' => 'required|integer|min:1', // ✅ min:1 pour éviter les 0
                'items.*.numero_bon_reception' => 'required|string|max:25',
                'items.*.date_reception' => 'required|date',
            ]);

            DB::beginTransaction();

            $achat = Achats::findOrFail($id);

            if ($achat->statut === Achats::ACHAT_ANNULE) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de réceptionner un achat annulé'
                ], 400);
            }

            // ✅ Parcourir UNIQUEMENT les items envoyés dans la requête
            foreach ($validated['items'] as $index => $itemData) {
                Log::info("Traitement item index {$index}:", $itemData);

                $item = AchatItems::findOrFail($itemData['id']);

                // Vérifier que l'item appartient bien à cet achat
                if ($item->achat_id !== $achat->id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "L'article {$item->id} n'appartient pas à cet achat"
                    ], 400);
                }

                // Vérifier que l'item n'est pas déjà reçu
                if ($item->isRecu()) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "L'article '{$item->nom_service}' a déjà été reçu"
                    ], 400);
                }

                $quantiteRecueTotale = $itemData['quantite_recu'];

                // Vérifier la quantité
                if ($quantiteRecueTotale > $item->quantite) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "La quantité reçue ({$quantiteRecueTotale}) dépasse la quantité commandée ({$item->quantite}) pour l'article '{$item->nom_service}'"
                    ], 422);
                }

                // ✅ Mettre à jour l'item
                $item->quantite_recu = $quantiteRecueTotale;
                $item->date_livraison = now();
                $item->numero_bon_reception = $itemData['numero_bon_reception'];
                $item->date_reception = $itemData['date_reception'];
                $item->prix_reel = $quantiteRecueTotale * $item->prix_unitaire;

                // ✅ Marquer comme reçu
                $item->marquerRecu();

                Log::info("✅ Item #{$item->id} traité - Statut: {$item->statut_item}");
            }

            // ✅ Mettre à jour le statut global de l'achat
            $achat->updateStatutGlobal();

            DB::commit();

            Log::info("=== FIN addBonReception - SUCCÈS ===");

            return response()->json([
                'success' => true,
                'message' => "Bon de réception ajouté avec succès pour " . count($validated['items']) . " article(s)",
                'data' => $achat->load(['items', 'fournisseur'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error("Erreur de validation:", $e->errors());

            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("=== ERREUR addBonReception ===");
            Log::error("Message : " . $e->getMessage());
            Log::error("Ligne : " . $e->getLine());
            Log::error("Fichier : " . $e->getFile());

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du bon de réception',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
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
                'items:id,achat_id,nom_service,quantite,quantite_recu,prix_unitaire,prix_total,prix_reel,date_commande,statut_item,numero_bon_reception,date_reception,date_livraison',
                'items.photos:id,achat_item_id,path'
            ])->select(
                'id',
                'fournisseur_id',
                'numero_achat',
                'bon_commande',
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

            // ✅ Transformer les chemins
            $getAchats->transform(function ($achat) {

                // 🔹 Transformer le bon de commande
                if ($achat->bon_commande) {
                    // Si le chemin commence déjà par 'http', on ne touche pas
                    if (!str_starts_with($achat->bon_commande, 'http')) {
                        // Si le chemin commence par 'storage/', on garde tel quel
                        if (str_starts_with($achat->bon_commande, 'storage/')) {
                            $achat->bon_commande = url($achat->bon_commande);
                        } else {
                            // Sinon on ajoute 'storage/' avant
                            $achat->bon_commande = url('storage/' . $achat->bon_commande);
                        }
                    }
                }

                // 🔹 Transformer les photos de chaque item
                $achat->items->transform(function ($item) {
                    // Transformer les photos de l'item
                    if ($item->photos) {
                        $item->photos->transform(function ($photo) {
                            // Si le chemin commence déjà par 'http', on ne touche pas
                            if (!str_starts_with($photo->path, 'http')) {
                                // Si le chemin commence par 'storage/', on garde tel quel
                                if (str_starts_with($photo->path, 'storage/')) {
                                    $photo->path = url($photo->path);
                                } else {
                                    // Sinon on ajoute 'storage/' avant
                                    $photo->path = url('storage/' . $photo->path);
                                }
                            }
                            return $photo;
                        });
                    }

                    // Transformer le bon de réception
                    if ($item->bon_reception) {
                        if (!str_starts_with($item->bon_reception, 'http')) {
                            if (str_starts_with($item->bon_reception, 'storage/')) {
                                $item->bon_reception = url($item->bon_reception);
                            } else {
                                $item->bon_reception = url('storage/' . $item->bon_reception);
                            }
                        }
                    }

                    return $item;
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
                'statut' => 'sometimes|string',
                'description' => 'sometimes|nullable|string',

                // ITEMS EXISTANTS UNIQUEMENT
                'items' => 'sometimes|array',
                'items.*.id' => 'required|exists:achat_items,id', // IMPORTANT : empêche la création !
                'items.*.nom_service' => 'sometimes|string|max:500',
                'items.*.quantite' => 'sometimes|integer|min:1',
                'items.*.prix_unitaire' => 'sometimes|numeric|min:0',
                'items.*.date_commande' => 'sometimes|date',
                'items.*.date_livraison' => 'nullable|date',

                // PHOTOS ITEM
                'items.*.photos' => 'sometimes|array|max:4',
                'items.*.photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
                'items.*.photos_to_delete' => 'sometimes|array',
            ]);

            DB::beginTransaction();

            $achat = Achats::with('items.photos')->findOrFail($id);

            // 🔹 Mise à jour des infos simples de l'achat
            $achat->update([
                'fournisseur_id' => $validated['fournisseur_id'] ?? $achat->fournisseur_id,
                'statut' => $validated['statut'] ?? $achat->statut,
                'description' => $validated['description'] ?? $achat->description,
            ]);

            $totalDepenses = 0;

            // 🔹 Mise à jour des items existants uniquement
            if ($request->has('items')) {
                foreach ($validated['items'] as $itemData) {
                    $item = $achat->items()->find($itemData['id']);

                    if (!$item) continue;

                    // Suppression des photos
                    if (!empty($itemData['photos_to_delete'])) {
                        foreach ($itemData['photos_to_delete'] as $photoId) {
                            $photo = $item->photos()->find($photoId);
                            if ($photo) {
                                $filePath = str_replace('storage/', '', $photo->path);
                                if (Storage::disk('public')->exists($filePath)) {
                                    Storage::disk('public')->delete($filePath);
                                }
                                $photo->delete();
                            }
                        }
                    }

                    // Mise à jour des champs simples
                    $item->update([
                        'nom_service' => $itemData['nom_service'] ?? $item->nom_service,
                        'quantite' => $itemData['quantite'] ?? $item->quantite,
                        'prix_unitaire' => $itemData['prix_unitaire'] ?? $item->prix_unitaire,
                        'date_commande' => $itemData['date_commande'] ?? $item->date_commande,
                        'date_livraison' => $itemData['date_livraison'] ?? $item->date_livraison,
                    ]);

                    // Recalcul
                    $item->prix_total = $item->calculePrixTotal();
                    $item->save();

                    $totalDepenses += $item->prix_total;

                    // Ajout de nouvelles photos
                    if (isset($itemData['photos'])) {
                        foreach ($itemData['photos'] as $photo) {
                            $filename = time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                            $path = $photo->storeAs("achats/items/{$item->id}", $filename, 'public');

                            $item->photos()->create([
                                'path' => 'storage/' . $path,
                                'achat_id' => $achat->id,
                                'achat_item_id' => $item->id,
                            ]);
                        }
                    }
                }
            }

            // Mise à jour des dépenses
            $achat->update(['depenses_total' => $totalDepenses]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Achat mis à jour avec succès",
                'data' => $achat->fresh(['items.photos', 'fournisseur']),
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
                'message' => "Erreur lors de la modification",
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

            // ✅ Vérifier si l'item existe avant findOrFail
            $item = AchatItems::find($id);

            if (!$item) {
                Log::warning("achat introuvable : ID {$id}");

                // Donner plus d'infos pour le debug
                $itemsExistants = AchatItems::pluck('id')->toArray();

                return response()->json([
                    'success' => false,
                    'message' => "article #{$id} introuvable",
                    'debug' => [
                        'item_recherche' => $id,
                        'items_existants' => $itemsExistants,
                        'total_items' => count($itemsExistants)
                    ]
                ], 404);
            }

            // Charger la relation achat
            $item->load('achat');

            // Vérifier si l'item est déjà annulé
            if ($item->isAnnule()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet article est déjà annulé"
                ], 400);
            }

            // ✅ Vérifier si l'item est en attente (condition obligatoire)
            if (!$item->isEnAttente()) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible d'annuler cet article. Seuls les articles en attente peuvent être annulés.",
                    'data' => [
                        'statut_actuel' => $item->statut_item,
                        'quantite_commandee' => $item->quantite,
                        'quantite_recue' => $item->quantite_recu
                    ]
                ], 400);
            }

            // Annuler l'item
            $item->marquerAnnule();

            return response()->json([
                'success' => true,
                'message' => "Article annulé avec succès",
                'data' => [
                    'item' => $item->fresh(),
                    'achat_statut' => $item->achat->statut
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Article introuvable:', [
                'item_id' => $id,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Article #{$id} introuvable dans la base de données"
            ], 404);
        } catch (\Exception $e) {
            Log::error('Erreur annulation item:', [
                'item_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
