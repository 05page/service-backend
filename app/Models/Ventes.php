<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Ventes extends Model
{
    protected $fillable = [
        'reference',
        'nom_client',
        'numero',
        'adresse',
        'commissionaire',
        'prix_total',
        'montant_verse',
        'reglement_statut',
        'statut',
        'created_by'
    ];

    protected $casts = [
        'prix_total' => 'decimal:2',
        'montant_verse' => 'decimal:2',
        'reglement_statut' => 'boolean',
    ];

    const STATUT_EN_ATTENTE = 'en attente';
    const STATUT_PAYE = 'paye';
    const STATUT_ANNULE = 'annulé';

    // ========== RELATIONS ==========
    public function items(): HasMany
    {
        return $this->hasMany(VenteItems::class, 'vente_id');
    }
    
    public function paiements()
    {
        return $this->morphMany(Paiement::class, 'payable');
    }
    
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function commissionnaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commissionaire');
    }
    
    public function facture()
    {
        return $this->hasOne(Factures::class, 'vente_id');
    }
    
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_id');
    }
    
    public function recus()
    {
        return $this->hasMany(Recus::class, 'vente_id');
    }
    
    public function commissions()
    {
        return $this->hasMany(Commission::class, 'ventes_id');
    }

    // ========== MÉTHODES DE VÉRIFICATION DU RÈGLEMENT ==========
    public function estSoldee(): bool
    {
        return $this->montant_verse >= $this->prix_total;
    }

    public function estPartiellementPayee(): bool
    {
        return $this->montant_verse > 0 && $this->montant_verse < $this->prix_total;
    }

    public function montantRestant(): float
    {
        return max(0, $this->prix_total - $this->montant_verse);
    }

    public function pourcentagePaye(): float
    {
        if ($this->prix_total <= 0) {
            return 0;
        }
        return round(($this->montant_verse / $this->prix_total) * 100, 2);
    }

    // ========== MÉTHODES DE PAIEMENT ==========
    public function ajouterPaiement(float $montant, $userId = null): bool
    {
        if ($montant <= 0) {
            return false;
        }

        return DB::transaction(function () use ($montant, $userId) {
            Paiement::create([
                'payable_id' => $this->id,
                'payable_type' => self::class,
                'montant_verse' => $montant,
                'created_by' => $userId ?? Auth::id()
            ]);

            $this->montant_verse += $montant;
            
            if ($this->estSoldee()) {
                $this->reglement_statut = 1;
                $this->statut = self::STATUT_PAYE;
            }

            return $this->save();
        });
    }

    // ========== GESTION DES COMMISSIONS ==========
    /**
     * Créer ou mettre à jour la commission pour cette vente
     */
    public function gererCommission(): void
    {
        if (!$this->commissionaire) {
            // Si pas de commissionnaire, supprimer toute commission existante
            $this->commissions()->delete();
            return;
        }

        $user = User::find($this->commissionaire);
        
        if (!$user || $user->taux_commission <= 0) {
            // Si l'utilisateur n'existe pas ou n'a pas de taux, supprimer la commission
            $this->commissions()->delete();
            return;
        }

        $montantCommission = ($this->prix_total * $user->taux_commission) / 100;
        
        // Vérifier si une commission existe déjà
        $commissionExistante = $this->commissions()->first();
        
        if ($commissionExistante) {
            // Mettre à jour la commission existante
            $commissionExistante->update([
                'user_id' => $user->id,
                'commission_due' => $montantCommission,
            ]);
        } else {
            // Créer une nouvelle commission
            Commission::create([
                'user_id' => $user->id,
                'ventes_id' => $this->id,
                'commission_due' => $montantCommission,
                'etat_commission' => 0,
            ]);
        }
    }

    // ========== SCOPES ==========
    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    public function scopePaye($query)
    {
        return $query->where('statut', self::STATUT_PAYE);
    }

    public function scopeRegle($query)
    {
        return $query->where('reglement_statut', 1);
    }

    public function scopeAnnule($query)
    {
        return $query->where('statut', self::STATUT_ANNULE);
    }

    public function scopeSoldees($query)
    {
        return $query->where('reglement_statut', 1);
    }

    public function scopeNonSoldees($query)
    {
        return $query->where('reglement_statut', 0);
    }

    // ========== MÉTHODES DE STATUT ==========
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

    // ========== CALCULS ==========
    public function calculerPrixTotal(): float
    {
        return $this->items()->sum('sous_total');
    }

    public function annuler(): bool
    {
        if ($this->isAnnule()) {
            return false;
        }

        return DB::transaction(function () {
            // Restaurer le stock pour chaque item
            foreach ($this->items as $item) {
                $stock = Stock::find($item->stock_id);
                if ($stock) {
                    $stock->addStock($item->quantite);
                    $stock->updateStatut();
                }
            }

            // Supprimer les commissions associées
            $this->commissions()->delete();

            $this->statut = self::STATUT_ANNULE;
            return $this->save();
        });
    }

    // ========== BOOT ==========
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($vente) {
            // Générer la référence automatiquement
            $year = date('Y');
            $lastVente = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastVente ? intval(substr($lastVente->reference, -3)) : 0;
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            $vente->reference = "VEN-{$year}-{$nextNumber}";
        });

        static::created(function ($vente) {
            // Gérer la commission après la création
            $vente->gererCommission();
        });

        static::updating(function ($vente) {
            // Vérifier si le commissionnaire ou le prix_total a changé
            if ($vente->isDirty('commissionaire') || $vente->isDirty('prix_total')) {
                // La commission sera gérée dans l'événement 'updated'
            }
        });

        static::updated(function ($vente) {
            // Gérer la commission après la mise à jour
            if ($vente->wasChanged('commissionaire') || $vente->wasChanged('prix_total')) {
                $vente->gererCommission();
            }
        });
    }

    // ========== MÉTHODES UTILITAIRES ==========
    public function getResume(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'nom_client' => $this->nom_client,
            'numero_client' => $this->numero,
            'items_count' => $this->items->count(),
            'prix_total' => $this->prix_total,
            'montant_verse' => $this->montant_verse,
            'reste_a_payer' => $this->montantRestant(),
            'est_soldee' => $this->estSoldee(),
            'pourcentage_paye' => $this->pourcentagePaye(),
            'statut' => $this->statut,
            'created_by' => $this->creePar?->fullname,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }

    public function getDetailsComplets(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            
            // Informations client
            'client' => [
                'nom' => $this->nom_client,
                'telephone' => $this->numero,
                'adresse' => $this->adresse
            ],
            
            // Articles vendus
            'articles' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nom' => $item->stock->achat->nom_service ?? 'Article',
                    'code' => $item->stock->code_produit ?? 'N/A',
                    'quantite' => $item->quantite,
                    'prix_unitaire' => $item->prix_unitaire,
                    'sous_total' => $item->sous_total
                ];
            }),
            
            // Informations financières
            'finances' => [
                'prix_total' => $this->prix_total,
                'montant_verse' => $this->montant_verse,
                'reste_a_payer' => $this->montantRestant(),
                'pourcentage_paye' => $this->pourcentagePaye(),
                'est_soldee' => $this->estSoldee()
            ],
            
            // Commissionnaire
            'commissionnaire' => $this->commissionnaire ? [
                'nom' => $this->commissionnaire->fullname,
                'taux' => $this->commissionnaire->taux_commission,
                'montant' => ($this->prix_total * $this->commissionnaire->taux_commission) / 100
            ] : null,
            
            // Métadonnées
            'statut' => $this->statut,
            'reglement_statut' => $this->reglement_statut,
            'cree_par' => $this->creePar?->fullname,
            'cree_le' => $this->created_at?->format('d/m/Y H:i'),
            'modifie_le' => $this->updated_at?->format('d/m/Y H:i')
        ];
    }
}