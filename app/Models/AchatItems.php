<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchatItems extends Model
{
    //
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

    //Définition des relations
    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achats::class);
    }
    public function photos(): BelongsTo
    {
        return $this->belongsTo(AchatPhotos::class);
    }

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

    /**Calculons le prix total */
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
    public function marquerRecu(): bool {   
        // ✅ Déterminer automatiquement le statut
        if ($this->quantite_recu >= $this->quantite) {
            $this->statut_item = self::STATUT_RECU;
        } elseif ($this->quantite_recu > 0) {
            $this->statut_item = self::STATUT_PARTIEL;
        } else {
            $this->statut_item = self::STATUT_EN_ATTENTE;
        }

        return $this->save();
    }

    protected static function boot()
    {
        parent::boot();
        
    }
}
