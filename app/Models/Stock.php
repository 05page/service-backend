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
        'achat_id',
        'code_produit',
        'numero_achat',
        'categorie',
        'quantite',
        'quantite_min',
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
    // public function fournisseur(): BelongsTo
    // {
    //     return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
    // }

    /** fonction pour filtrer les produits */

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

     // Scope pour charger automatiquement l'achat
    public function scopeWithAchat($query)
    {
        return $query->with('achat:id,nom_service');
    }

    public function scopeStockFaible($query)
    {
        return $query->where('quantite', '<=', self::STOCK_FAIBLE);
    }

    public function scopeRupture($query)
    {
        return $query->where('quantite', '=', 0);
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
        $this->actif = $this->quantite > 0;
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

        if ($this->isFaible()) {
            return 'alerte';
        }

        return 'disponible';
    }

    public function updateStatut(): bool
    {
        $this->statut = $this->getStatutStock();
        return $this->save();
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
            'code_produit' => $this->code_produit,
            'categorie' => $this->categorie,
            'quantite' => $this->quantite,
            'quantite_min' => $this->quantite_min,
            'prix_vente' => $this->prix_vente,
            'description' => $this->description,
            'statut' => $this->getStatutStock(),
            'actif' => $this->actif,

            // Relations
            'achat' => $this->achat?->nom_service, // grâce au belongsTo
            'cree_par' => $this->creePar?->fullname,

            // Dates
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
