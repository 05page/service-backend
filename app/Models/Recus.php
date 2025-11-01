<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use User;

class Recus extends Model
{
    //
    protected $table = 'recus';
    protected $fillable = [
        'numero_recu',
        'vente_id',
        'paiement_id',
        'montant_paye',
        'montant_cumule',
        'reste_a_payer',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'montant_paye' => 'decimal:2',
        'montant_cumule' => 'decimal:2',
        'reste_a_payer' => 'decimal:2',
    ];

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Ventes::class);
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($recu) {
            $year = date('Y');
            $lastRecu = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastRecu ? intval(substr($lastRecu->numero_recu, -4)) : 0;
            $nextNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            $recu->numero_recu = "REC-{$year}-{$nextNumber}";
        });
    }
}
