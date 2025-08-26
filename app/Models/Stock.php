<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    //
    protected $table = "stock";
    protected $fillable = [
        'service_id',
        'fournisseur_id',
        'quantite', // Note: Corrigé la faute de frappe de la migration
        'nom_produit',
        'actif',
        'created_by'
    ];

    protected $casts = [
        'actif' => 'boolean',
        'quantite' => 'integer',
    ];

    // Constantes pour les seuils
    const STOCK_CRITIQUE = 5;
    const STOCK_FAIBLE = 10;

    /**
     * Relation avec l'utilisateur qui a créé l'entrée stock
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec le service lié à ce produit
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    /**
     * Relation avec le fournisseur de ce produit
     */
    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
    }

    /**
     * Relation avec les achats (qui alimentent ce stock)
     */
    // public function achats(): HasMany
    // {
    //     return $this->hasMany(Achat::class, 'stock_id');
    // }
}
