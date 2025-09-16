<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Achats;
use App\Models\Factures;
use App\Models\Permissions;
use App\Models\User;
use App\Models\Ventes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class FacturesController extends Controller
{
    /**
     * Vérification des permissions pour le module factures
     */
    private function verifierPermissions(): bool
    {
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN) {
            /** @var User $user */
            $hasPermission = $user->permissions()
                ->where('module', Permissions::MODULE_FACTURES)
                ->where('active', true)->exists();
            return $hasPermission;
        }
        return true;
    }

    /**
     * Génère une facture PDF directement depuis une vente payée
     * Action unique : Création en base + PDF téléchargé immédiatement
     */
    public function generateFacturePDFFromVente($venteId)
    {
        try {
            // Vérification des permissions
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            // Récupérer la vente avec toutes les relations nécessaires
            $vente = Ventes::with([
                'stock:id,nom_produit,prix_vente,code_produit',
                'creePar:id,fullname,email'
            ])->findOrFail($venteId);

            // Vérifier que la vente est payée (règle métier stricte)
            if ($vente->statut !== Ventes::STATUT_PAYE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer une facture pour une vente non payée.'
                ], 422);
            }

            // Vérification facture existante
            $factureExistante = Factures::where('vente_id', $venteId)->first();
            if ($factureExistante) {
                return response()->json([
                    'success' => false,
                    'message' => "Une facture existe déjà pour cette vente : {$factureExistante->numero_facture}"
                ], 422);
            }

            DB::beginTransaction();

            // Création de la facture en base
            $facture = Factures::create([
                'vente_id' => $venteId,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            // Préparation des données pour le PDF
            $donneesFacture = [
                'facture' => $facture,
                'vente' => $vente,
                'type_facture' => 'vente',
                'client' => [
                    'nom' => $vente->nom_client,
                    'telephone' => $vente->numero
                ],
                'articles' => [
                    [
                        'description' => $vente->stock->nom_produit,
                        'code' => $vente->stock->code_produit,
                        'quantite' => $vente->quantite,
                        'prix_unitaire' => $vente->stock->prix_vente,
                        'total' => $vente->prix_total
                    ]
                ],
                'totaux' => [
                    'sous_total' => $vente->prix_total,
                    'montant_total' => $vente->prix_total
                ],
                'entreprise' => [
                    'nom' => 'VOTRE ENTREPRISE',
                    'adresse' => '123 Rue de l\'Innovation',
                    'ville' => '75001 Paris, France',
                    'telephone' => '+33 1 23 45 67 89',
                    'email' => 'contact@entreprise.fr',
                    'siret' => '123 456 789 00012'
                ],
                'date_generation' => now()->format('d/m/Y')
            ];

            // Génération du PDF avec DomPDF
            $pdf = Pdf::loadView('factures.pdf', $donneesFacture)
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'Arial'
                ]);

            $nomFichier = "{$facture->numero_facture}.pdf";

            return $pdf->download($nomFichier);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vente introuvable'
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la facture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère une facture PDF directement depuis un achat reçu
     * Action unique : Création en base + PDF téléchargé immédiatement
     */
    public function generateFacturePDFFromAchat($achatId)
    {
        try {
            // Vérification des permissions
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            // Récupérer l'achat avec toutes les relations nécessaires
            $achat = Achats::with([
                'fournisseur:id,nom_fournisseurs,email,telephone,adresse',
                'creePar:id,fullname,email'
            ])->findOrFail($achatId);

            // Vérifier que l'achat est reçu (règle métier stricte)
            if ($achat->statut !== Achats::ACHAT_REÇU) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer une facture pour un achat non reçu.'
                ], 422);
            }

            // Vérification facture existante
            $factureExistante = Factures::where('achat_id', $achatId)->first();
            if ($factureExistante) {
                return response()->json([
                    'success' => false,
                    'message' => "Une facture existe déjà pour cet achat : {$factureExistante->numero_facture}"
                ], 422);
            }

            DB::beginTransaction();

            // Création de la facture en base
            $facture = Factures::create([
                'achat_id' => $achatId,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            // Préparation des données pour le PDF
            $donneesFacture = [
                'facture' => $facture,
                'achat' => $achat,
                'type_facture' => 'achat',
                'fournisseur' => [
                    'nom' => $achat->fournisseur->nom_fournisseurs,
                    'email' => $achat->fournisseur->email,
                    'telephone' => $achat->fournisseur->telephone,
                    'adresse' => $achat->fournisseur->adresse
                ],
                'articles' => [
                    [
                        'description' => $achat->nom_service,
                        'quantite' => $achat->quantite,
                        'prix_unitaire' => $achat->prix_unitaire,
                        'total' => $achat->prix_total
                    ]
                ],
                'totaux' => [
                    'sous_total' => $achat->prix_total,
                    'montant_total' => $achat->prix_total
                ],
                'entreprise' => [
                    'nom' => 'VOTRE ENTREPRISE',
                    'adresse' => '123 Rue de l\'Innovation',
                    'ville' => '75001 Paris, France',
                    'telephone' => '+33 1 23 45 67 89',
                    'email' => 'contact@entreprise.fr',
                    'siret' => '123 456 789 00012'
                ],
                'date_generation' => now()->format('d/m/Y')
            ];

            // Génération du PDF avec DomPDF
            $pdf = Pdf::loadView('factures.pdf', $donneesFacture)
                ->setPaper('A4', 'portrait')
                ->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'Arial'
                ]);

            $nomFichier = "{$facture->numero_facture}.pdf";

            return $pdf->download($nomFichier);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Achat introuvable'
            ], 404);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la facture',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister toutes les factures (consultation)
     */
    public function index(): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            $factures = Factures::with([
                'creePar:id,fullname,email',
                'vente:id,reference,nom_client,prix_total',
                'achat:id,numero_achat,nom_service,prix_total'
            ])->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $factures
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des factures',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher une facture spécifique (consultation)
     */
    public function show($id): JsonResponse
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            $facture = Factures::with([
                'creePar:id,fullname,email',
                'vente:id,reference,nom_client,numero,quantite,prix_total',
                'vente.stock:id,nom_produit,code_produit,prix_vente',
                'achat:id,numero_achat,nom_service,quantite,prix_total',
                'achat.fournisseur:id,nom_fournisseurs,email,telephone,adresse'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $facture
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Facture introuvable'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la facture',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}