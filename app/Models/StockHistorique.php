<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistorique extends Model
{
    protected $table = 'stock_historiques';
    
    protected $fillable = [
        'stock_id',
        'achats_id',  // ✅ On garde 'achats_id'
        'type',
        'quantite',
        'quantite_avant',
        'quantite_apres',
        'commentaire',
        'created_by'
    ];

    protected $casts = [
        'quantite' => 'integer',
        'quantite_avant' => 'integer',
        'quantite_apres' => 'integer',
    ];

    // Types de mouvements
    const TYPE_ENTREE = 'entree';
    const TYPE_SORTIE = 'sortie';
    const TYPE_RENOUVELLEMENT = 'renouvellement';
    const TYPE_AJUSTEMENT = 'ajustement';
    const TYPE_CREATION = 'creation';

    /**
     * Relation avec le stock
     */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    /**
     * ✅ Relation avec l'achat - Utiliser 'achats_id' comme foreign key
     */
    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achats::class, 'achats_id');
    }

    /**
     * Relation avec l'utilisateur créateur
     */
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scopes pour filtrer par type
     */
    public function scopeEntrees($query)
    {
        return $query->whereIn('type', [self::TYPE_ENTREE, self::TYPE_RENOUVELLEMENT, self::TYPE_CREATION]);
    }

    public function scopeSorties($query)
    {
        return $query->where('type', self::TYPE_SORTIE);
    }

    public function scopeRenouvellements($query)
    {
        return $query->where('type', self::TYPE_RENOUVELLEMENT);
    }

    /**
     * Obtenir le résumé de l'historique
     */
    public function getResume(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'quantite' => $this->quantite,
            'quantite_avant' => $this->quantite_avant,
            'quantite_apres' => $this->quantite_apres,
            'achat' => $this->achat?->numero_achat,
            'commentaire' => $this->commentaire,
            'date' => $this->created_at?->format('d/m/Y H:i'),
            'par' => $this->creePar?->fullname
        ];
    }

    /**
     * Obtenir le libellé du type
     */
    public function getTypeLibelle(): string
    {
        return match($this->type) {
            self::TYPE_ENTREE => 'Entrée en stock',
            self::TYPE_SORTIE => 'Sortie de stock',
            self::TYPE_RENOUVELLEMENT => 'Renouvellement',
            self::TYPE_AJUSTEMENT => 'Ajustement',
            self::TYPE_CREATION => 'Création initiale',
            default => 'Inconnu'
        };
    }
}