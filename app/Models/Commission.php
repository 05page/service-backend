<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Commission extends Model
{
    //
    protected $fillable = [
        'user_id',
        'ventes_id',
        'commission_due',
        'etat_commission'
    ];

    protected $casts = [
        "commission_due" => "decimal:2",
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    //Relation 
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Ventes::class, 'ventes_id');
    }

    // Relation polymorphe avec les paiements
    public function paiements(): MorphMany
    {
        return $this->morphMany(Paiement::class, 'payable');
    }

    //Scopes
    //Commission en attente
    public function scopeAttente($query)
    {
        return $query->where('etat_commission', 0);
    }

    //Commission réglée
    public function scopePayees($query)
    {
        return $query->where('etat_commission', 1);
    }

    // Commissions non réglées
    public function scopeCommissionsDues($query)
    {
        return $query->where('etat_commission', 0);
    }

    // Commissions réglées
    public function scopeCommissionsPayees($query)
    {
        return $query->where('etat_commission', 1);
    }


    public function getResume(): array
    {
        return  [
            'id' => $this->id,
            'ventes_id' => $this->ventes_id,
            'commission_due' => $this->commission_due,
            'etat_commission' => $this->etat_commission
        ];
    }
}
