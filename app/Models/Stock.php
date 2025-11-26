<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achats::class, 'achat_id');
    }

    public function achatItem()
    {
        // Si vous avez une relation directe via achat_id
        return $this->hasOneThrough(
            AchatItems::class,
            Achats::class,
            'id',        // Foreign key sur achats
            'achat_id',  // Foreign key sur achat_items
            'achat_id',  // Local key sur stock
            'id'         // Local key sur achats
        )->oldest(); // Prendre le premier item
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AchatPhotos::class, 'achat_id');
    }

    /**
     * Relation avec l'historique (stock_historiques.stock_id)
     */
    public function historiques(): HasMany
    {
        return $this->hasMany(StockHistorique::class, 'stock_id');
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

    // Helpers
    public function isActif(): bool
    {
        return (bool) $this->actif;
    }

    public function isDisponible(): bool
    {
        return $this->isActif() && $this->quantite > 0;
    }

    public function isFaible(): bool
    {
        return $this->quantite <= self::STOCK_FAIBLE;
    }

    public function isStockRupture(): bool
    {
        return $this->quantite == self::STOCK_RUPTURE;
    }

    /**
     * Valeur calculée du stock (cohérente avec tes champs)
     */
    public function getStockActuel(): int
    {
        return (int) ($this->entre_stock - $this->sortie_stock);
    }

    /**
     * Ajoute de la quantité (entrée simple)
     */
    public function addStock(int $quantite): bool
    {
        $this->increment('entre_stock', $quantite);
        $this->increment('quantite', $quantite);
        $this->updateStatut();
        return true;
    }

    /**
     * Retire de la quantité (sortie simple)
     */
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

    /**
     * Enregistrer une sortie (avec historique)
     */
    public function enregistrerSortie(int $quantite, ?string $commentaire = null): bool
    {
        if ($quantite > $this->quantite) {
            return false;
        }

        DB::beginTransaction();

        try {
            $quantiteAvant = $this->quantite;

            $this->retirerStock($quantite);

            // créer historique
            $this->historiques()->create([
                'type' => StockHistorique::TYPE_SORTIE,
                'quantite' => $quantite,
                'quantite_avant' => $quantiteAvant,
                'quantite_apres' => $this->quantite,
                'commentaire' => $commentaire ?? "Sortie de stock",
                'created_by' => auth()->id() ?? $this->created_by
            ]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Renouveler le stock en utilisant un ACHAT existant.
     */
    public function renouvelerStock(Achats $achat, ?string $commentaire = null): bool
    {
        DB::beginTransaction();

        try {
            // sauvegarde qty avant
            $quantiteAvant = $this->quantite;

            // mettre à jour la référence d'achat et les compteurs
            $this->achat_id = $achat->id;
            $this->numero_achat = $achat->numero_achat ?? $this->numero_achat;
            $this->increment('entre_stock', $achat->quantite);
            $this->increment('quantite', $achat->quantite);

            // refresh model values (optionnel)
            $this->refresh();

            // Créer l'historique du renouvellement
            $this->historiques()->create([
                'achat_id' => $achat->id,
                'type' => StockHistorique::TYPE_RENOUVELLEMENT,
                'quantite' => $achat->quantite,
                'quantite_avant' => $quantiteAvant,
                'quantite_apres' => $this->quantite,
                'commentaire' => $commentaire ?? "Renouvellement via achat {$achat->numero_achat}",
                'created_by' => auth()->id() ?? $this->created_by
            ]);

            // mettre à jour statut si besoin
            $this->updateStatut();

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    /**
     * Récupérer tous les achats liés à ce stock via l'historique
     */
    public function getTousLesAchats()
    {
        return Achats::whereHas('stockHistoriques', function ($query) {
            $query->where('stock_id', $this->id);
        })->with('fournisseur')->get();
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

        static::created(function ($stock) {
            // créer automatiquement l'historique de création si achat existant
            $stock->historiques()->create([
                'achat_id' => $stock->achat_id,
                'type' => StockHistorique::TYPE_CREATION,
                'quantite' => $stock->quantite,
                'quantite_avant' => 0,
                'quantite_apres' => $stock->quantite,
                'commentaire' => "Création initiale du stock",
                'created_by' => $stock->created_by
            ]);
        });

        static::updating(function ($stock) {
            if ($stock->quantite == 0 && $stock->achat) {
                $stock->achat->update(['active' => false]);
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
