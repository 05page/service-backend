<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AchatItems;
use App\Models\Achats;
use App\Models\Factures;
use App\Models\Permissions;
use App\Models\Recus;
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

    public function genererDocumentVente($venteId)
    {
        try {
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé."
                ], 403);
            }

            $vente = Ventes::with([
                'items.stock',
                'creePar:id,fullname,email',
                'commissionnaire:id,fullname,taux_commission'
            ])->findOrFail($venteId);

            // ✅ Logique : Facture si soldée, Reçu si partiel
            if ($vente->estSoldee()) {
                return $this->genererFacture($vente);
            } else {
                return $this->genererRecu($vente);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getEntrepriseInfo(): array
    {
        return [
            'nom' => config('app.entreprise_nom', 'VOTRE ENTREPRISE'),
            'adresse' => config('app.entreprise_adresse', '123 Rue de l\'Innovation'),
            'ville' => config('app.entreprise_ville', 'Abidjan, Côte d\'Ivoire'),
            'telephone' => config('app.entreprise_tel', '+225 XX XX XX XX XX'),
            'email' => config('app.entreprise_email', 'contact@entreprise.ci'),
            'siret' => config('app.entreprise_siret', 'XXX XXX XXX XXXXX')
        ];
    }

    private function genererFacture(Ventes $vente)
    {
        if (!$vente->estSoldee()) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de générer une facture pour une vente non soldée.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // ✅ Vérifier si facture existe déjà
            $factureExistante = Factures::where('vente_id', $vente->id)->first();

            if ($factureExistante) {
                // ✅ Incrémenter le compteur de copies
                $factureExistante->increment('nombre_copies');
                $nombreCopies = $factureExistante->nombre_copies;

                DB::commit();
                return $this->telechargerFacture($factureExistante, $vente, $nombreCopies);
            }

            // ✅ Créer la facture pour la première fois (ORIGINAL)
            $facture = Factures::create([
                'vente_id' => $vente->id,
                'created_by' => Auth::id(),
                'nombre_copies' => 0, // 0 = ORIGINAL
            ]);

            DB::commit();

            return $this->telechargerFacture($facture, $vente, 0);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function telechargerFacture(Factures $facture, Ventes $vente, int $nombreCopies = 0)
    {
        $donneesFacture = [
            'facture' => $facture,
            'vente' => $vente,
            'type_document' => 'facture',
            'statut_copie' => $nombreCopies === 0 ? 'ORIGINAL' : "COPIE N°{$nombreCopies}",
            'client' => [
                'nom' => $vente->nom_client,
                'telephone' => $vente->numero,
                'adresse' => $vente->adresse
            ],
            'articles' => $vente->items->map(function ($item) {
                return [
                    'description' => $item->stock->achatItem->nom_service ?? 'Article',
                    'code' => $item->stock->code_produit ?? 'N/A',
                    'quantite' => $item->quantite,
                    'prix_unitaire' => $item->prix_unitaire,
                    'total' => $item->sous_total
                ];
            })->toArray(),
            'totaux' => [
                'sous_total' => $vente->prix_total,
                'montant_total' => $vente->prix_total,
                'montant_verse' => $vente->montant_verse,
                'reste_a_payer' => 0
            ],
            'entreprise' => $this->getEntrepriseInfo(),
            'date_generation' => now()->format('d/m/Y')
        ];

        $pdf = Pdf::loadView('factures.pdf', $donneesFacture)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

        $nomFichier = $nombreCopies === 0
            ? "{$facture->numero_facture}.pdf"
            : "{$facture->numero_facture}_COPIE_{$nombreCopies}.pdf";

        return $pdf->download($nomFichier);
    }

    private function genererRecu(Ventes $vente)
    {
        if ($vente->montant_verse <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun paiement enregistré pour cette vente.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Récupérer le dernier paiement
            $dernierPaiement = $vente->paiements()->latest()->first();

            // ✅ Vérifier si un reçu existe déjà pour cette vente
            $recuExistant = Recus::where('vente_id', $vente->id)->first();

            if ($recuExistant) {
                // ✅ Incrémenter le compteur de copies
                $recuExistant->increment('nombre_copies');
                $nombreCopies = $recuExistant->nombre_copies;

                DB::commit();
                return $this->telechargerRecu($recuExistant, $vente, $nombreCopies);
            }

            // ✅ Créer le reçu pour la première fois (ORIGINAL)
            $recu = Recus::create([
                'vente_id' => $vente->id,
                'paiement_id' => $dernierPaiement->id ?? null,
                'montant_paye' => $dernierPaiement->montant_verse ?? $vente->montant_verse,
                'montant_cumule' => $vente->montant_verse,
                'reste_a_payer' => $vente->montantRestant(),
                'created_by' => Auth::id(),
                'nombre_copies' => 0, // 0 = ORIGINAL
            ]);

            DB::commit();

            return $this->telechargerRecu($recu, $vente, 0);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function telechargerRecu(Recus $recu, Ventes $vente, int $nombreCopies = 0)
    {
        $donneesRecu = [
            'recu' => $recu,
            'vente' => $vente,
            'type_document' => 'recu',
            'statut_copie' => $nombreCopies === 0 ? 'ORIGINAL' : "COPIE N°{$nombreCopies}",
            'client' => [
                'nom' => $vente->nom_client,
                'telephone' => $vente->numero,
                'adresse' => $vente->adresse
            ],
            'articles' => $vente->items->map(function ($item) {
                return [
                    'description' => $item->stock->achatItem->nom_service ?? 'Article',
                    'code' => $item->stock->code_produit ?? 'N/A',
                    'quantite' => $item->quantite,
                    'prix_unitaire' => $item->prix_unitaire,
                    'total' => $item->sous_total
                ];
            })->toArray(),
            'paiement' => [
                'montant_paye' => $recu->montant_paye,
                'montant_cumule' => $recu->montant_cumule,
                'reste_a_payer' => $recu->reste_a_payer,
                'pourcentage_paye' => $vente->pourcentagePaye()
            ],
            'totaux' => [
                'sous_total' => $vente->prix_total,
                'montant_total' => $vente->prix_total,
                'montant_verse' => $vente->montant_verse,
                'reste_a_payer' => $recu->reste_a_payer
            ],
            'intermediaire' => $vente->commissionnaire ? [
                'nom' => $vente->commissionnaire->fullname,
                'commission' => ($vente->prix_total * $vente->commissionnaire->taux_commission) / 100
            ] : null,
            'entreprise' => $this->getEntrepriseInfo(),
            'date_generation' => now()->format('d/m/Y')
        ];

        $pdf = Pdf::loadView('factures.pdf', $donneesRecu)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
            ]);

        $nomFichier = $nombreCopies === 0
            ? "{$recu->numero_recu}.pdf"
            : "{$recu->numero_recu}_COPIE_{$nombreCopies}.pdf";

        return $pdf->download($nomFichier);
    }

    /**
     * Génère une facture PDF directement depuis un achat reçu
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

            // ✅ CORRECTION : Charger aussi les items
            $achat = Achats::with([
                'fournisseur:id,nom_fournisseurs,email,telephone,adresse',
                'creePar:id,fullname,email',
                'items' // ✅ Ajouter cette relation
            ])->findOrFail($achatId);

            // Vérifier que l'achat est reçu
            if ($achat->statut !== Achats::ACHAT_REÇU && $achat->statut !== Achats::ACHAT_PARTIEL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer une facture pour un achat non reçu.'
                ], 422);
            }

            DB::beginTransaction();

            // Vérification facture existante
            $factureExistante = Factures::where('achat_id', $achatId)->first();

            if ($factureExistante) {
                $factureExistante->increment('nombre_copies');
                $nombreCopies = $factureExistante->nombre_copies;

                DB::commit();

                // ✅ CORRECTION : Enlever $items du paramètre
                $donneesFacture = $this->preparerDonneesFactureAchat($factureExistante, $achat, $nombreCopies);

                return $this->telechargerFactureAchat($factureExistante, $donneesFacture, $nombreCopies);
            }

            // Création de la facture
            $facture = Factures::create([
                'achat_id' => $achatId,
                'created_by' => Auth::id(),
                'nombre_copies' => 0,
            ]);

            DB::commit();

            // ✅ CORRECTION : Enlever $items du paramètre
            $donneesFacture = $this->preparerDonneesFactureAchat($facture, $achat, 0);

            return $this->telechargerFactureAchat($facture, $donneesFacture, 0);
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

    // ✅ CORRECTION : Enlever le paramètre $items et utiliser $achat->items
    private function preparerDonneesFactureAchat(Factures $facture, Achats $achat, int $nombreCopies = 0): array
    {
        // Préparer les articles depuis les items de l'achat
        $articles = [];
        foreach ($achat->items as $item) {
            $articles[] = [
                'description' => $item->nom_service,
                'numero_achat' => $achat->numero_achat,
                'quantite' => $item->quantite_recu,
                'prix_unitaire' => $item->prix_unitaire,
                'total' => $item->prix_total
            ];
        }

        return [
            'facture' => $facture,
            'achat' => $achat,
            'type_document' => 'achat',
            'statut_copie' => $nombreCopies === 0 ? 'ORIGINAL' : "COPIE N°{$nombreCopies}",
            'fournisseur' => [
                'nom' => $achat->fournisseur->nom_fournisseurs,
                'email' => $achat->fournisseur->email,
                'telephone' => $achat->fournisseur->telephone,
                'adresse' => $achat->fournisseur->adresse
            ],
            'articles' => $articles, // ✅ Utiliser le tableau d'articles construit
            'totaux' => [
                'sous_total' => $achat->depenses_total, // ✅ CORRECTION : depenses_total (avec 's')
                'montant_total' => $achat->depenses_total
            ],
            'entreprise' => $this->getEntrepriseInfo(),
            'date_generation' => now()->format('d/m/Y')
        ];
    }

    private function telechargerFactureAchat(Factures $facture, array $donneesFacture, int $nombreCopies = 0)
    {
        $pdf = Pdf::loadView('factures.pdf', $donneesFacture)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => true,
                'defaultFont' => 'Arial',
                'margin-top' => 10,
                'margin-bottom' => 10,
                'margin-left' => 10,
                'margin-right' => 10,
            ]);

        $nomFichier = $nombreCopies === 0
            ? "{$facture->numero_facture}.pdf"
            : "{$facture->numero_facture}_COPIE_{$nombreCopies}.pdf";

        return $pdf->download($nomFichier);
    }

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
}
