<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achats extends Model
{
    //
    protected $fillable = [
        'fournisseur_id',
        'nom_service',
        'quantite',
        'prix_unitaire',
        'prix_total',
        'numero_achat',
        'date_commande',
        'date_livraison',
        'statut',
        'active',
        'description',
        'created_by'
    ];

    protected $casts = [
        'quantite' => 'integer'
    ];

    const ACHAT_COMMANDE = "commande";
    const ACHAT_REÇU = "reçu";
    const ACHAT_PAYE = "paye";
    const ACHAT_ANNULE = "annule";

    /**Relation avec l'utilisateur qui crée l'achat */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Relation entre l'achat et le fournisseur*/
    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
    }

    public function stock()
    {
        // Un achat peut avoir un seul stock lié
        return $this->hasOne(Stock::class, 'achat_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'achat_id');
    }

    public function photos()
    {
        return $this->hasMany(AchatPhotos::class, 'achat_id');
    }

    /** Filtrer les achats */

    public function scopeCommande($query)
    {
        return $query->where('statut', self::ACHAT_COMMANDE);
    }

    public function scopeReçu($query)
    {
        return $query->where('statut', self::ACHAT_REÇU);
    }

    public function scopePaye($query)
    {
        return $query->where('statut', self::ACHAT_PAYE);
    }

    public function scopeAnnule($query)
    {
        return $query->where('statut', self::ACHAT_PAYE);
    }

    /**Methode helpers */
    public function isReçu(): bool
    {
        return $this->statut === self::ACHAT_REÇU;
    }

    public function isCommande(): bool
    {
        return $this->statut === self::ACHAT_COMMANDE;
    }

    public function isPaye(): bool
    {
        return $this->statut === self::ACHAT_PAYE;
    }

    public function isAnnule(): bool
    {
        return $this->statut === self::ACHAT_ANNULE;
    }

    /**Calculons le prix unitaire */
    public function calculePrixTotal(): float
    {
        if (!$this->quantite || $this->quantite <= 0) {
            return 0;
        }

        return $this->prix_unitaire * $this->quantite;
    }

    /**Marquer comme confirmer */
    public function marqueReçu(): bool
    {
        if (!$this->isCommande()) {
            return false;
        }

        $this->statut = self::ACHAT_REÇU;
        return $this->save();
    }

    /**Marquer comme payé */
    public function marquePaye(): bool
    {
        if (!$this->isCommande()) {
            return false;
        }

        $this->statut = self::ACHAT_PAYE;
        return $this->save();
    }

    /**Marquer comme annulé */
    public function marqueAnnule(): bool
    {
        if (!$this->isCommande()) {
            return false;
        }

        $this->statut = self::ACHAT_ANNULE;
        return $this->save();
    }

    // ✅ NOUVEAU : Mettre à jour le stock lié automatiquement
    public function mettreAJourStockLie(): void
    {
        $stock = $this->stock;

        if ($stock) {
            // Calculer la différence de quantité
            $ancienneQuantite = $stock->quantite;
            $nouvelleQuantite = $this->quantite;
            $difference = $nouvelleQuantite - $ancienneQuantite;

            // Mettre à jour le stock
            $stock->quantite = $nouvelleQuantite;
            $stock->entre_stock = $nouvelleQuantite;

            // Mettre à jour le statut du stock
            $stock->updateStatut();
            $stock->save();
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($achat) {
            $year = date('Y');
            $lastAchat = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastAchat ? intval(substr($lastAchat->numero_achat, -3)) : 0;
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

            $achat->numero_achat = "ACH-{$year}-{$nextNumber}";
        });

        // ✅ AJOUTÉ : Mettre à jour le stock automatiquement
        static::updated(function ($achat) {
            if ($achat->wasChanged('quantite')) {
                $achat->mettreAJourStockLie();
            }
        });
    }

    public function getResume(): array
    {
        return [
            'id' => $this->id,
            'fournisseur_id' => $this->fournisseur_id,
            'nom_service' => $this->nom_service,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'prix_total' => $this->prix_total,
            'numero_achat' => $this->numero_achat,
            'date_commande' => $this->date_commande,
            'date_livraison' => $this->date_livraison,
            'statut' => $this->statut,
            'mode_paiement' => $this->mode_paiement,
            'description' => $this->description,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
