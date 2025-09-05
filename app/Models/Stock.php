<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Stock extends Model
{
    //
    protected $table = "stock";
    protected $fillable = [
        'nom_produit',
        'code_prodnumero_uit',
        'numero_achat',
        'achat_id',
        'categorie',
        'fournisseur_id',
        'quantite', // Note: Corrigé la faute de frappe de la migration
        'quantite_min',
        'prix_achat',
        'prix_vente',
        'description',
        'statut',
        'actif',
        'created_by'
    ];

    protected $casts = [
        'actif' => 'boolean',
        'quantite' => 'integer',
        'quantite_min' => 'integer'
    ];

    // Constantes pour les seuils
    const STOCK_FAIBLE = 2;
    const STOCK_RUPTURE = 0;

    /**
     * Relation avec l'utilisateur qui a créé l'entrée stock
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relation avec le fournisseur de ce produit
     */
    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
    }

    /** fonction pour filtrer les produits */

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParFournisseur($query, $fournisseurId)
    {
        return $query->where('fournisseur_id', $fournisseurId);
    }

    public function scopeStockFaible($query)
    {
        return $query->where('quantite', '<=', self::STOCK_FAIBLE);
    }

    public function scopeStockDisponible($query)
    {
        return $query->where('quantite', '>', 0);
    }

    /**méthode helper */


    /** Vérifions si le produit est actif */

    public function isActif(): bool
    {
        return $this->actif;
    }

    /**Verifions que le stock est disponible */
    public function isDisponible(): bool
    {
        return $this->actif && $this->quantite > 0;
    }

    /**Vérifions que le stock est faible */
    public function isFaible(): bool
    {
        return $this->quantite <= self::STOCK_FAIBLE;
    }
    /** verifions que le stocks est en repture */

    public function isStockRupture(): bool
    {
        return $this->quantite == self::STOCK_RUPTURE;
    }

    /**Désactiver le produit */
    public function desactiver(): bool
    {
        $this->actif = false;
        return $this->save();
    }

    /**Activer le produit */
    public function reactiver(): bool
    {
        $this->actif = true;
        return $this->save();
    }

    public function retirerStock(int $quantite): bool
    {
        if ($quantite <= 0 || $this->quantite < $quantite) {
            return false;
        }

        $this->quantite -= $quantite;
        $this->statut = $this->getStatutStock();
        $this->actif = false;
        return $this->save();
    }

    public function addStock(int $quantite): bool
    {
        if ($quantite <= 0) {
            return false;
        }

        $this->quantite += $quantite;
        return $this->save();
    }

    /**Obtenir le statut du stock */
    public function getStatutStock(): string
    {
        if (!$this->actif) {
            return 'inactif';
        }

        if ($this->quantite == 0) {
            return 'rupture';
        }

        if ($this->isStockFaible()) {
            return 'alert';
        }

        return 'disponible';
    }

    public function updateStatut(): bool{
        $this->statut = $this->getStatutStock();
        return $this->save();
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achats::class, 'stock_id');
    }

    public function achat()
    {
        // Un stock est lié à un seul achat
        return $this->belongsTo(Achats::class, 'achat_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($stock) {
            $year = date('Y');
            $lastStock = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastStock ? intval(substr($lastStock->code_produit, -3)) : 0;
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

            $stock->code_produit = "STCK-{$year}-{$nextNumber}";
        });
    }

    public function getResume(): array
    {
        return [
            'id' => $this->id,
            'nom_produit' => $this->nom_produit,
            'code_produit' => $this->code_produit,
            'categorie' => $this->categorie,
            'fournisseur' => $this->fournisseur?->nom_fournisseurs,
            'quantite' => $this->quantite,
            'quantite_min' => $this->quantie_min,
            'prix_achat' => $this->prix_achat,
            'prix_vente' => $this->prix_vente,
            'description' => $this->description,
            'statut' => $this->getStatutStock(),
            'cree_par' => $this->creePar?->fullname,
            'actif' => $this->actif,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
