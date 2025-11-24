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
        'date_livraison' => 'date'
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

    public function scopeDisponible($query)
    {
        $achatEnStock = Stock::pluck('achat_id');
        return $query->whereIn('statut_item', [
            self::STATUT_RECU,
            self::STATUT_PARTIEL,
        ])->whereNotIn('achat_id', $achatEnStock);
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
     * ✅ MÉTHODE CORRIGÉE : Sans transaction imbriquée
     */
    public function addStock()
    {
        try {
            Log::info("=== DÉBUT addStock() pour item #{$this->id} ===");

            // Ne rien faire si aucune quantité n'a été reçue
            if ($this->quantite_recu <= 0) {
                Log::warning("Quantité reçue = 0, annulation");
                return;
            }

            // Vérifier que l'achat existe
            $achat = $this->achat;
            if (!$achat) {
                Log::error("Achat introuvable pour l'item #{$this->id}");
                return;
            }

            Log::info("Achat trouvé : #{$achat->id} - {$achat->numero_achat}");

            // Chercher le stock existant
            $stock = Stock::where('achat_id', $this->achat_id)->first();

            if ($stock) {
                Log::info("Stock existant trouvé : #{$stock->id}");
            } else {
                Log::info("Aucun stock trouvé, création nécessaire");
            }

            // ✅ PAS DE TRANSACTION ICI - On est déjà dans une transaction du contrôleur
            if ($stock) {
                // ✅ CAS 1 : Renouvellement
                Log::info("Renouvellement du stock #{$stock->id} avec {$this->quantite_recu} unités");

                $quantiteAvant = $stock->quantite;

                $stock->increment('entre_stock', $this->quantite_recu);
                $stock->increment('quantite', $this->quantite_recu);
                $stock->updateStatut();

                // ✅ Créer l'historique avec 'achats_id'
                $stock->historiques()->create([
                    'achat_id' => $this->achat_id,
                    'type' => StockHistorique::TYPE_RENOUVELLEMENT,
                    'quantite' => $this->quantite_recu,
                    'quantite_avant' => $quantiteAvant,
                    'quantite_apres' => $stock->quantite,
                    'commentaire' => "Renouvellement automatique depuis l'item #{$this->id} - {$this->nom_service}",
                    'created_by' => auth()->id() ?? $achat->created_by
                ]);

                Log::info("✅ Renouvellement réussi : {$quantiteAvant} → {$stock->quantite}");
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

                Log::info("✅ Stock créé : #{$nouveauStock->id} - {$nouveauStock->code_produit}");
            }

            Log::info("=== FIN addStock() - SUCCÈS ===");
        } catch (\Exception $e) {
            Log::error("=== ERREUR dans addStock() ===");
            Log::error("Message : " . $e->getMessage());
            Log::error("Ligne : " . $e->getLine());
            Log::error("Fichier : " . $e->getFile());

            // ✅ Ne pas faire de rollback ici - laisser le contrôleur gérer
            throw $e; // Propager l'erreur au contrôleur
        }
    }

    /**
     * ✅ Événements du modèle
     */
    protected static function boot()
    {
        parent::boot();

        /**
         * Événement UPDATED
         */
        static::updated(function ($item) {
            Log::info("=== Événement UPDATED item #{$item->id} ===");

            // Vérifier les changements
            $quantiteRecueAChange = $item->wasChanged('quantite_recu');

            Log::info("Quantité changée : " . ($quantiteRecueAChange ? 'OUI' : 'NON'));

            if ($quantiteRecueAChange) {
                Log::info("Ancienne quantité : " . $item->getOriginal('quantite_recu'));
                Log::info("Nouvelle quantité : " . $item->quantite_recu);
            }

            // Vérifier le statut
            $estRecu = in_array($item->statut_item, [
                self::STATUT_RECU,
                self::STATUT_PARTIEL
            ]);

            Log::info("Statut valide : " . ($estRecu ? 'OUI' : 'NON'));
            Log::info("Statut actuel : " . $item->statut_item);

            // Déclencher l'ajout au stock
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
