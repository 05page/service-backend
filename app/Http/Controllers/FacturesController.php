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
            //Vérifier si facture existe déjà
            $factureExistante = Factures::where('vente_id', $vente->id)->first();

            if ($factureExistante) {
                //Incrémenter le compteur de copies
                $factureExistante->increment('nombre_copies');
                $nombreCopies = $factureExistante->nombre_copies;

                DB::commit();
                return $this->telechargerFacture($factureExistante, $vente, $nombreCopies);
            }

            //Créer la facture pour la première fois (ORIGINAL)
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

            //Chargement des items
            $achat = Achats::with([
                'fournisseur:id,nom_fournisseurs,email,telephone,adresse',
                'creePar:id,fullname,email',
                'items'
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

    /**
     * Génère une facture PDF basée sur un bon de réception
     * @param int $achatItemId - ID de l'item d'achat (bon de réception)
     */
    public function genererFactureDepuisBonReception($achatItemId)
    {
        try {
            // Vérification des permissions
            if (!$this->verifierPermissions()) {
                return response()->json([
                    'success' => false,
                    'message' => "Accès refusé. Vous n'avez pas la permission pour cette action."
                ], 403);
            }

            // Récupérer l'item avec ses relations
            $achatItem = AchatItems::with([
                'achat.fournisseur:id,nom_fournisseurs,email,telephone,adresse',
                'achat.creePar:id,fullname'
            ])->findOrFail($achatItemId);

            // Vérifier que l'item a bien un bon de réception
            if (empty($achatItem->numero_bon_reception) || empty($achatItem->date_reception)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet article ne possède pas de bon de réception valide.'
                ], 422);
            }

            // Vérifier que l'item est reçu
            if (!in_array($achatItem->statut_item, [AchatItems::STATUT_RECU, AchatItems::STATUT_PARTIEL])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer une facture pour un item non reçu.'
                ], 422);
            }

            DB::beginTransaction();

            // Vérifier si une facture existe déjà pour cet item
            // Note: Nous utilisons achat_id car la table factures n'a pas de colonne achat_item_id
            // Vous pourriez vouloir ajouter cette colonne ou utiliser une autre logique
            $factureExistante = Factures::where('achat_id', $achatItem->achat_id)->first();

            if ($factureExistante) {
                $factureExistante->increment('nombre_copies');
                $nombreCopies = $factureExistante->nombre_copies;

                DB::commit();

                $donneesFacture = $this->preparerDonneesFactureBonReception($factureExistante, $achatItem, $nombreCopies);
                return $this->telechargerFactureBonReception($factureExistante, $donneesFacture, $nombreCopies);
            }

            // Créer une nouvelle facture
            $facture = Factures::create([
                'achat_id' => $achatItem->achat_id,
                'created_by' => Auth::id(),
                'nombre_copies' => 0,
            ]);

            DB::commit();

            $donneesFacture = $this->preparerDonneesFactureBonReception($facture, $achatItem, 0);
            return $this->telechargerFactureBonReception($facture, $donneesFacture, 0);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bon de réception introuvable'
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
     * Prépare les données pour la facture du bon de réception
     */
    private function preparerDonneesFactureBonReception(Factures $facture, AchatItems $achatItem, int $nombreCopies = 0): array
    {
        // Préparer l'article
        $article = [
            'description' => $achatItem->nom_service,
            'numero_bon_reception' => $achatItem->numero_bon_reception,
            'date_reception' => $achatItem->date_reception ? $achatItem->date_reception->format('d/m/Y') : 'N/A',
            'quantite' => $achatItem->quantite_recu,
            'prix_unitaire' => $achatItem->prix_unitaire,
            'total' => $achatItem->prix_unitaire * $achatItem->quantite_recu
        ];

        return [
            'facture' => $facture,
            'achatItem' => $achatItem,
            'achat' => $achatItem->achat,
            'type_document' => 'bon_reception',
            'statut_copie' => $nombreCopies === 0 ? 'ORIGINAL' : "COPIE N°{$nombreCopies}",
            'fournisseur' => [
                'nom' => $achatItem->achat->fournisseur->nom_fournisseurs,
                'email' => $achatItem->achat->fournisseur->email,
                'telephone' => $achatItem->achat->fournisseur->telephone,
                'adresse' => $achatItem->achat->fournisseur->adresse
            ],
            'articles' => [$article], // Tableau avec un seul article
            'bon_reception' => [
                'numero' => $achatItem->numero_bon_reception,
                'date' => $achatItem->date_reception ? $achatItem->date_reception->format('d/m/Y') : 'N/A'
            ],
            'totaux' => [
                'sous_total' => $article['total'],
                'montant_total' => $article['total']
            ],
            'entreprise' => $this->getEntrepriseInfo(),
            'date_generation' => now()->format('d/m/Y')
        ];
    }

    /**
     * Télécharge la facture du bon de réception en PDF
     */
    private function telechargerFactureBonReception(Factures $facture, array $donneesFacture, int $nombreCopies = 0)
    {
        $pdf = Pdf::loadView('factures.bon_reception_facture', $donneesFacture)
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
            ? "FACTURE_BR_{$donneesFacture['bon_reception']['numero']}.pdf"
            : "FACTURE_BR_{$donneesFacture['bon_reception']['numero']}_COPIE_{$nombreCopies}.pdf";

        return $pdf->download($nomFichier);
    }

}
