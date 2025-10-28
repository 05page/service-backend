<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenteItems extends Model
{
    //
    protected $fillable = [
        'vente_id',
        'stock_id',
        'quantite',
        'prix_unitaire',
        'sous_total'
    ];
    
    public function vente(): BelongsTo
    {
        return $this->belongsTo(Ventes::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            // Calculer automatiquement le sous-total
            $item->sous_total = $item->quantite * $item->prix_unitaire;
        });

        static::updating(function ($item) {
            if ($item->isDirty(['quantite', 'prix_unitaire'])) {
                $item->sous_total = $item->quantite * $item->prix_unitaire;
            }
        });
    }
}
