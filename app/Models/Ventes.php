<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ventes extends Model
{
    //
    protected $fillable = [
        'service_id',
        'nom_client',
        'numero', // Numéro téléphone client
        'quantite',
        'prix_total',
        'statut',
        'created_by'
    ];

    protected $casts = [
        'prix_total' => 'decimal:2',
        'quantite' => 'integer',
    ];

    // Constantes pour les statuts
    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_PAYE = 'payé';
    const STATUT_ANNULE = 'annulé';
    
    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    /**
     * Relation avec l'utilisateur qui a créé la vente
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec les factures générées pour cette vente
     */
    // public function factures(): HasMany
    // {
    //     return $this->hasMany(Facture::class, 'vente_id');
    // }    
}
