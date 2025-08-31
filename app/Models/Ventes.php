<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class Ventes extends Model
{
    //
    protected $fillable = [
        'stock_id',
        'reference',
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

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }

    /**
     * Relation avec l'utilisateur qui a créé la vente
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSatut($query, $statut)
    {
        return $query->where('satut', $statut);
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    public function scopePaye($query)
    {
        return $query->where('statut', self::STATUT_PAYE);
    }

    public function scopeAnnule($query)
    {
        return $query->where('statut', self::STATUT_ANNULE);
    }

    public function scopeStock($query, $stockId)
    {
        return $query->where('stock_id', $stockId);
    }

    public function scopeClient($query, $nomClient)
    {
        return $query->where('nom_client', 'like', "%{$nomClient}%");
    }

    /** */

    public function isEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    public function isPaye(): bool
    {
        return $this->statut === self::STATUT_PAYE;
    }

    public function isAnnule(): bool
    {
        return $this->statut === self::STATUT_ANNULE;
    }

    /**Marquer une vente comme payé */
    public function marquerPaye(): bool
    {
        if (!$this->isEnAttente()) {
            return false;
        }

        $this->statut = self::STATUT_PAYE;
        return $this->save();
    }

    /**Marquer une vente comme annulé */
    public function annuler(): bool
    {
        if ($this->isAnnule()) {
            return false; // Déjà annulée
        }

        return DB::transaction(function () {
            // Si la vente avait impacté le stock, on le restaure
            if ($this->quantite > 0) {
                $stock = $this->getStockAssocie();
                if ($stock) {
                    $stock->addStock($this->quantite);
                }
            }
            $this->statut = self::STATUT_ANNULE;
            return $this->save();
        });
    }

    /** Gestion de stock */

    public function getStockAssocie()
    {
        return Stock::where('id', $this->stock_id)
            ->where('actif', true)
            ->first();
    }

    public function verifyStock(): bool
    {
        if (!$this->quantite || $this->quantite <= 0) {
            return true; // Pas de stock requis
        }

        $stock = $this->getStockAssocie();
        if (!$stock) {
            return false; // Pas de stock configuré pour ce service
        }

        return $stock->quantite >= $this->quantite;
    }

    public function impacterStock(): bool
    {
        if (!$this->quantite || $this->quantite <= 0) {
            return true; // Pas de stock à impacter
        }

        $stock = $this->getStockAssocie();
        if (!$stock) {
            return false; // Pas de stock configuré
        }

        return $stock->retirerStock($this->quantite);
    }

    /**
     * Calculer le montant total des ventes pour une période
     */
    public static function chiffreAffaires($dateDebut = null, $dateFin = null): float
    {
        $query = self::payees();

        if ($dateDebut) {
            $query->whereDate('created_at', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->whereDate('created_at', '<=', $dateFin);
        }

        return $query->sum('prix_total');
    }

    public function calculerPrixTotal(): float
    {
        if (!$this->stock || !$this->quantite) {
            return 0;
        }

        return $this->stock->prix_vente * $this->quantite;
    }

    protected static function boot()
    {
        parent::boot();

        parent::boot();
        static::creating(function ($vente) {
            $year = date('Y');

            $lastVente = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastVente ? intval(substr($lastVente->reference, -3)) : 0;

            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

            $vente->reference = "VEN-{$year}-{$nextNumber}";
        });

        // Avant la création
        static::creating(function ($vente) {
            // Si pas de prix_total fourni, on le calcule automatiquement
            if (!$vente->prix_total && $vente->stock_id && $vente->quantite) {
                // Charger le service si pas déjà fait
                if (!$vente->stock) {
                    $vente->load('stock');
                }
                $vente->prix_total = $vente->calculerPrixTotal();
            }
        });

        // Lors de la création d'une vente
        static::created(function ($vente) {
            // Impacter le stock automatiquement si en attente ou payé
            if ($vente->statut !== self::STATUT_ANNULE) {
                $vente->impacterStock();
            }
        });

        // Avant la mise à jour
        static::updating(function ($vente) {
            // Si la quantité ou le service change, recalculer le prix
            if ($vente->isDirty(['quantite', 'stock_id']) && !$vente->isDirty('prix_total')) {
                if (!$vente->stock) {
                    $vente->load('stock');
                }
                $vente->prix_total = $vente->calculerPrixTotal();
            }
        });
    }

    /**
     * Obtenir un résumé de la vente
     */
    public function getResume(): array
    {
        return [
            'id' => $this->id,
            // 'service' => $this->service?->nom_service,
            'nom_client' => $this->nom_client,
            'numero_client' => $this->numero,
            'quantite' => $this->quantite,
            'prix_total' => $this->prix_total,
            'statut' => $this->statut,
            'created_by' => $this->creePar?->fullname,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
