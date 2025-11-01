<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Paiement extends Model
{
    //
    protected $table = 'paiements';
    protected $fillable = [
        'payable_type',
        'payable_id',
        'montant_verse',
        'date_paiement',
        'methode',
        'created_by',
    ];

    protected $casts = [
        'date_paiement' => 'datetime', // ✅ important
    ];
      /**
     * Relation polymorphique vers Ventes, Achats, etc.
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Utilisateur qui a créé le paiement
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getResumePaiement(): array
    {
        return [
            'id'=> $this->id,
            'payable_type'=>$this->payable_type,
            'payable_id' => $this->payable_id,
            'montant_verse'=> $this->montant_verse,
            'date_paiement'=> $this->date_paiement,
            'methode'=> $this->methode,
            'created_by'=> $this->created_by
        ];
    }
}
