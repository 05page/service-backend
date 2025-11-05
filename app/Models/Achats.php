<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Achats extends Model
{
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

    /**
     * ✅ MODIFIÉ : Garder pour compatibilité avec le code existant
     * Cette relation retourne le PREMIER stock lié via l'historique
     */
    public function stock()
    {
        return $this->hasOneThrough(
            Stock::class,
            StockHistorique::class,
            'achat_id', // Foreign key sur stock_historiques
            'id', // Foreign key sur stock
            'id', // Local key sur achats
            'stock_id' // Local key sur stock_historiques
        )->oldest(); // Prendre le premier stock lié
    }

    /**
     * ✅ NOUVEAU : Relation pour obtenir tous les historiques de stock
     */
    public function stockHistoriques(): HasMany
    {
        return $this->hasMany(StockHistorique::class, 'achats_id');  // ✅ Utiliser 'achats_id'
    }

    /**
     * ✅ NOUVEAU : Vérifier si cet achat est déjà utilisé dans un stock
     */
    public function estUtiliseDansStock(): bool
    {
        return $this->stockHistoriques()->exists();
    }

    /**
     * ✅ NOUVEAU : Obtenir tous les stocks liés à cet achat
     */
    public function getTousLesStocks()
    {
        return Stock::whereHas('historiques', function ($query) {
            $query->where('achats_id', $this->id);  // ✅ Utiliser 'achats_id'
        })->get();
    }

    public function photos(): HasMany
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
        return $query->where('statut', self::ACHAT_ANNULE);
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

    /**Calculons le prix total */
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

    /**
     * Marquer comme annulé et retirer le stock associé
     */
    public function marqueAnnule(): bool
    {
        if ($this->isAnnule()) {
            return false;
        }

        return DB::transaction(function () {
            // ✅ Vérifier si cet achat est utilisé dans un stock
            if ($this->estUtiliseDansStock()) {
                $stocksLies = $this->getTousLesStocks();

                foreach ($stocksLies as $stock) {
                    // Récupérer la quantité totale ajoutée par cet achat
                    $quantiteTotale = $stock->historiques()
                        ->where('achats_id', $this->id)
                        ->whereIn('type', ['creation', 'renouvellement', 'entree'])
                        ->sum('quantite');

                    if ($quantiteTotale > 0 && $stock->quantite >= $quantiteTotale) {
                        $quantiteAvant = $stock->quantite;

                        // Retirer la quantité du stock
                        $stock->quantite -= $quantiteTotale;
                        $stock->entre_stock -= $quantiteTotale;
                        $stock->updateStatut();
                        $stock->save();

                        // ✅ Créer une entrée d'historique
                        $stock->historiques()->create([
                            'achats_id' => null,
                            'type' => 'sortie',
                            'quantite' => $quantiteTotale,
                            'quantite_avant' => $quantiteAvant,
                            'quantite_apres' => $stock->quantite,
                            'commentaire' => "Retrait suite à l'annulation de l'achat {$this->numero_achat}",
                            'created_by' => auth()->id() ?? $this->created_by
                        ]);
                    }
                }
            }

            // Marquer l'achat comme annulé
            $this->statut = self::ACHAT_ANNULE;
            $this->active = false;

            return $this->save();
        });
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
            'description' => $this->description,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
