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
        'statut',
        'description',
        'depenses_total',
        'bon_commande',
        'active',
        'created_by'
    ];

    protected $casts = [
        'quantite' => 'integer'
    ];

    const ACHAT_COMMANDE = "commande";
    const ACHAT_REÇU = "reçu";
    const ACHAT_PARTIEL = "partiellement_recu";
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
    
    public function items(): HasMany
    {
        return $this->hasMany(AchatItems::class, 'achat_id');
    }

    public function stock()
    {
        return $this->hasOneThrough(
            Stock::class,
            StockHistorique::class,
            'achat_id',
            'id',
            'id',
            'stock_id'
        )->oldest('stock_historiques.created_at'); // ✅ Spécifier la table
    }

    public function stockHistoriques(): HasMany
    {
        return $this->hasMany(StockHistorique::class, 'achat_id');
    }

    /**
     * ✅ MÉTHODE CORRIGÉE : Vérifier si cet achat est déjà utilisé dans un stock
     */
    public function estUtiliseDansStock(): bool
    {
        // Vérifie directement dans la table stocks
        return Stock::where('achat_id', $this->id)->exists();
    }

    /**
     * ✅ MÉTHODE CORRIGÉE : Obtenir tous les stocks liés à cet achat
     */
    public function getTousLesStocks()
    {
        // Méthode simple et directe
        return Stock::where('achat_id', $this->id)->get();
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
        return $query->whereIn('statut', [self::ACHAT_REÇU, self::ACHAT_PARTIEL]);
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

    public function isPartiel(): bool
    {
        return $this->statut === self::ACHAT_PARTIEL;
    }

    public function isCommande(): bool
    {
        return $this->statut === self::ACHAT_COMMANDE;
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

    /**
     * ✅ MÉTHODE AMÉLIORÉE : Mise à jour du statut en fonction des items
     */
    public function updateStatutGlobal(): void
    {
        $totalItems = $this->items()->count();

        if ($totalItems === 0) {
            $this->statut = self::ACHAT_COMMANDE;
            $this->save();
            return;
        }

        // Compter les items par statut
        $itemsRecus = $this->items()
            ->where('statut_item', AchatItems::STATUT_RECU)
            ->count();

        $itemsPartiels = $this->items()
            ->where('statut_item', AchatItems::STATUT_PARTIEL)
            ->count();

        $itemsAnnules = $this->items()
            ->where('statut_item', AchatItems::STATUT_ANNULE)
            ->count();

        $itemsEnAttente = $this->items()
            ->where('statut_item', AchatItems::STATUT_EN_ATTENTE)
            ->count();

        // ✅ LOGIQUE DE STATUT AMÉLIORÉE
        
        // Cas 1 : Tous les items sont annulés
        if ($itemsAnnules === $totalItems) {
            $this->statut = self::ACHAT_ANNULE;
            $this->active = false;
        }
        // Cas 2 : Tous les items NON annulés sont reçus
        elseif ($itemsRecus === ($totalItems - $itemsAnnules) && ($totalItems - $itemsAnnules) > 0) {
            $this->statut = self::ACHAT_REÇU;
        }
        // Cas 3 : Au moins un item est reçu ou partiel (et pas tous annulés)
        elseif (($itemsRecus > 0 || $itemsPartiels > 0) && $itemsAnnules < $totalItems) {
            $this->statut = self::ACHAT_PARTIEL;
        }
        // Cas 4 : Que des items en attente (ou mélange attente + annulés)
        else {
            $this->statut = self::ACHAT_COMMANDE;
        }

        $this->save();
    }

    /**
     * ✅ MÉTHODE CORRIGÉE : Annuler l'achat (seulement si en commande)
     */
    public function marqueAnnule(): bool
    {
        if ($this->isAnnule()) {
            return false;
        }

        // ✅ VÉRIFICATION : On ne peut annuler QUE les achats en commande
        if (!$this->isCommande()) {
            Log::error("Impossible d'annuler l'achat #{$this->id} - Statut actuel : {$this->statut}");
            throw new \Exception("Impossible d'annuler cet achat. Seuls les achats en commande peuvent être annulés.");
        }

        return DB::transaction(function () {
            // ✅ Annuler tous les items en attente
            $itemsEnAttente = $this->items()
                ->where('statut_item', AchatItems::STATUT_EN_ATTENTE)
                ->get();

            foreach ($itemsEnAttente as $item) {
                $item->marquerAnnule();
            }

            // ✅ Le statut sera mis à jour automatiquement par updateStatutGlobal()
            $this->updateStatutGlobal();

            return true;
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
            'numero_achat' => $this->numero_achat,
            'statut' => $this->statut,
            'description' => $this->description,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}