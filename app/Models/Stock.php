<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Stock extends Model
{
    protected $table = "stock";
    protected $fillable = [
        'achat_id',
        'code_produit',
        'numero_achat',
        'categorie',
        'quantite',
        'quantite_min',
        'entre_stock',
        'sortie_stock',
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

    const STOCK_FAIBLE = 2;
    const STOCK_RUPTURE = 0;

    // Relations
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function achat()
    {
        return $this->belongsTo(Achats::class, 'achat_id');
    }

        public function photos()
    {
        return $this->hasMany(AchatPhotos::class, 'achat_id');
    }

    // Scopes
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

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

    public function scopeEntre($query)
    {
        return $query->where('entre_stock', '>', 0);
    }

    public function scopeSortie($query)
    {
        return $query->where('sortie_stock', '>', 0);
    }

    // Méthodes helpers
    public function isActif(): bool
    {
        return $this->actif;
    }

    public function isDisponible(): bool
    {
        return $this->actif && $this->quantite > 0;
    }

    public function isFaible(): bool
    {
        return $this->quantite <= self::STOCK_FAIBLE;
    }

    public function isStockRupture(): bool
    {
        return $this->quantite == self::STOCK_RUPTURE;
    }

    public function getStockActuel(): int
    {
        return $this->entre_stock - $this->sortie_stock;
    }

    public function addStock(int $quantite): bool
    {
        $this->increment('entre_stock', $quantite);
        $this->increment('quantite', $quantite);
        return true;
    }

    public function retirerStock(int $quantite): bool
    {
        $this->increment('sortie_stock', $quantite);
        $this->decrement('quantite', $quantite);
        $this->updateStatut();
        return true;
    }

    public function getStatutStock(): string
    {
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

    // ✅ NOUVEAU : Renouveler le stock via un nouvel achat
    public function renouvelerStock(int $achatId, int $quantiteSupplementaire): bool
    {
        $nouvelAchat = Achats::find($achatId);
        
        if (!$nouvelAchat) {
            return false;
        }

        // Ajouter la quantité au stock existant
        $this->increment('entre_stock', $quantiteSupplementaire);
        $this->increment('quantite', $quantiteSupplementaire);
        
        // Mettre à jour le statut
        $this->updateStatut();
        
        return true;
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

        static::updating(function($stock){
            if($stock->quantite == 0 && $stock->achat){
                $stock->achat->update(['active'=>false]);
            }
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
            'sortie_stock' => $this->sortie_stock,
            'entre_stock' => $this->entre_stock,
            'prix_vente' => $this->prix_vente,
            'description' => $this->description,
            'statut' => $this->getStatutStock(),
            'actif' => $this->actif,
            'achat' => $this->achat?->nom_service,
            'cree_par' => $this->creePar?->fullname,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
