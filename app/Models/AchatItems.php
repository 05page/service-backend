<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchatItems extends Model
{
    protected $fillable = [
        'achat_id',
        'nom_service',
        'quantite',
        'quantite_recu',
        'prix_unitaire',
        'prix_total',
        'prix_reel',
        'date_commande',
        'bon_reception',
        'statut_item',
        'date_livraison'
    ];

    protected $casts = [
        'date_commande' => 'date',
        'date_livraison' => 'date',
        'date_reception' => 'date'
    ];

    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_PARTIEL = 'partiellement_recu';
    const STATUT_RECU = 'recu';
    const STATUT_ANNULE = 'annule';

    // Relations
    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achats::class);
    }

    public function photos()
    {
        return $this->hasMany(AchatPhotos::class, 'achat_item_id');
    }

    /**
     * ✅ NOUVELLE RELATION : Vers les historiques de stock via achat_id
     */
    public function stockHistoriques()
    {
        return $this->hasMany(StockHistorique::class, 'achat_id', 'achat_id');
    }

    // Helpers
    public function isRecu(): bool
    {
        return $this->statut_item === self::STATUT_RECU;
    }

    public function isPartiel(): bool
    {
        return $this->statut_item === self::STATUT_PARTIEL;
    }

    public function isEnAttente(): bool
    {
        return $this->statut_item === self::STATUT_EN_ATTENTE;
    }

    public function estUtiliseDansStock(): bool
    {
        return $this->stockHistoriques()->exists();
    }

    /**
     * ✅ CORRECTION DU SCOPE
     */
    public function scopeDisponible($query)
    {
        return $query->whereIn('statut_item', [
            self::STATUT_RECU,
            self::STATUT_PARTIEL,
        ])->whereDoesntHave('stockHistoriques'); // ✅ Syntaxe correcte
    }

    public function calculePrixTotal(): float
    {
        if (!$this->quantite || $this->quantite <= 0) {
            return 0;
        }
        return $this->prix_unitaire * $this->quantite;
    }

    public function quantiteRestante(): int
    {
        return max(0, $this->quantite - $this->quantite_recu);
    }

    /**
     * Marquer cet item comme reçu (totalement ou partiellement)
     */
    public function marquerRecu(): bool
    {
        if ($this->quantite_recu >= $this->quantite) {
            $this->statut_item = self::STATUT_RECU;
        } elseif ($this->quantite_recu > 0) {
            $this->statut_item = self::STATUT_PARTIEL;
        } else {
            $this->statut_item = self::STATUT_EN_ATTENTE;
        }

        return $this->save();
    }

    /**
     * ✅ MÉTHODE CORRIGÉE : Gestion correcte du stock via historiques
     */
    public function addStock()
    {
        try {
            Log::info("=== DÉBUT addStock() pour item #{$this->id} ===");

            if ($this->quantite_recu <= 0) {
                Log::warning("Quantité reçue = 0, annulation");
                return;
            }

            $achat = $this->achat;
            if (!$achat) {
                Log::error("Achat introuvable pour l'item #{$this->id}");
                return;
            }

            Log::info("Achat trouvé : #{$achat->id} - {$achat->numero_achat}");

            // ✅ CORRECTION : Chercher via stock_historiques
            $stockExistant = Stock::whereHas('historiques', function($query) {
                $query->where('achat_id', $this->achat_id);
            })->first();

            if ($stockExistant) {
                // ✅ CAS 1 : Renouvellement
                Log::info("Renouvellement du stock #{$stockExistant->id} avec {$this->quantite_recu} unités");

                $quantiteAvant = $stockExistant->quantite;

                $stockExistant->increment('entre_stock', $this->quantite_recu);
                $stockExistant->increment('quantite', $this->quantite_recu);
                $stockExistant->updateStatut();

                $stockExistant->historiques()->create([
                    'achat_id' => $this->achat_id,
                    'type' => StockHistorique::TYPE_RENOUVELLEMENT,
                    'quantite' => $this->quantite_recu,
                    'quantite_avant' => $quantiteAvant,
                    'quantite_apres' => $stockExistant->quantite,
                    'commentaire' => "Renouvellement automatique depuis l'item #{$this->id} - {$this->nom_service}",
                    'created_by' => auth()->id() ?? $achat->created_by
                ]);

                Log::info("✅ Renouvellement réussi : {$quantiteAvant} → {$stockExistant->quantite}");
            } else {
                // ✅ CAS 2 : Création
                Log::info("Création d'un nouveau stock pour '{$this->nom_service}'");

                $nouveauStock = Stock::create([
                    'achat_id' => $this->achat_id,
                    'categorie' => 'Non défini',
                    'quantite' => $this->quantite_recu,
                    'quantite_min' => 1,
                    'entre_stock' => $this->quantite_recu,
                    'sortie_stock' => 0,
                    'prix_vente' => $this->prix_unitaire * 1.3,
                    'description' => "Stock créé automatiquement depuis l'achat #{$achat->numero_achat}",
                    'statut' => 'disponible',
                    'actif' => true,
                    'created_by' => auth()->id() ?? $achat->created_by
                ]);

                // ✅ IMPORTANT : Créer l'historique de création
                $nouveauStock->historiques()->create([
                    'achat_id' => $this->achat_id,
                    'type' => StockHistorique::TYPE_CREATION,
                    'quantite' => $this->quantite_recu,
                    'quantite_avant' => 0,
                    'quantite_apres' => $nouveauStock->quantite,
                    'commentaire' => "Création depuis l'item #{$this->id} - {$this->nom_service}",
                    'created_by' => auth()->id() ?? $achat->created_by
                ]);

                Log::info("✅ Stock créé : #{$nouveauStock->id} - {$nouveauStock->code_produit}");
            }

            Log::info("=== FIN addStock() - SUCCÈS ===");
        } catch (\Exception $e) {
            Log::error("=== ERREUR dans addStock() ===");
            Log::error("Message : " . $e->getMessage());
            Log::error("Ligne : " . $e->getLine());
            Log::error("Fichier : " . $e->getFile());
            Log::error("Stack trace : " . $e->getTraceAsString());

            throw $e;
        }
    }

    /**
     * ✅ Événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($item) {
            Log::info("=== Événement UPDATED item #{$item->id} ===");

            $quantiteRecueAChange = $item->wasChanged('quantite_recu');

            Log::info("Quantité changée : " . ($quantiteRecueAChange ? 'OUI' : 'NON'));

            if ($quantiteRecueAChange) {
                Log::info("Ancienne quantité : " . $item->getOriginal('quantite_recu'));
                Log::info("Nouvelle quantité : " . $item->quantite_recu);
            }

            $estRecu = in_array($item->statut_item, [
                self::STATUT_RECU,
                self::STATUT_PARTIEL
            ]);

            Log::info("Statut valide : " . ($estRecu ? 'OUI' : 'NON'));
            Log::info("Statut actuel : " . $item->statut_item);

            if ($quantiteRecueAChange && $estRecu) {
                Log::info("✅ Conditions remplies → Appel addStock()");
                $item->addStock();
            } else {
                Log::warning("❌ Conditions NON remplies");
                if (!$quantiteRecueAChange) {
                    Log::warning("→ quantite_recu n'a pas changé");
                }
                if (!$estRecu) {
                    Log::warning("→ statut invalide : {$item->statut_item}");
                }
            }
        });
    }
}