<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    //
    protected $fillable = [
        'payable_type',
        'payable_id',
        'montant_verse',
        'date_paiement',
        'methode',
        'created_by',
    ];

    public function getResumePaiement(): array
    {
        return [
            'id'=> $this->id,
            'payable_type'=>$this->payable_type,
            'paiement_id'=> $this->paiement_id,
            'montant_verse'=> $this->montant_verse,
            'date_paiement'=> $this->date_paiement,
            'methode'=> $this->methode,
            'created_by'=> $this->created_by
        ];
    }
}
