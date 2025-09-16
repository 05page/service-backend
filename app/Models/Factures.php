<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factures extends Model
{
    //

    protected $fillable = [
        'achat_id',
        'vente_id',
        'numero_facture',
        'created_by'
    ];

    protected $casts = [
        'description'=> 'json'
    ];

    //relations
    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achats::class, 'achat_id');
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Ventes::class, 'vente_id');
    }

    //scopes
    public function scopeFactureVente($query){
        return $query->whereNotNull('vente_id')->whereNull('achat_id');
    }

    public function scopeFactureAchat($query){
        return $query->whereNotNull('achat_id')->whereNull('vente_id');
    }
    
 public function isVenteFacture(): bool
    {
        return !is_null($this->vente_id) && is_null($this->achat_id);
    }

    public function isAchatFacture(): bool
    {
        return !is_null($this->achat_id) && is_null($this->vente_id);
    }

    public function getMontantTotal(): float
    {
        if ($this->isVenteFacture()) {
            return $this->vente->prix_total;
        }
        
        if ($this->isAchatFacture()) {
            return $this->achat->prix_total;
        }
        
        return 0;
    }

    public function getClient(): ?string
    {
        if ($this->isVenteFacture()) {
            return $this->vente->nom_client;
        }
        
        if ($this->isAchatFacture()) {
            return $this->achat->fournisseur->nom_fournisseurs;
        }
        
        return null;
    }

    protected static function boot()
    {
        parent::boot();
        
        // Validation avant création/mise à jour
        static::creating(function ($facture) {
            self::validateFactureLogic($facture);
        });
        
        static::updating(function ($facture) {
            self::validateFactureLogic($facture);
        });
        
        static::creating(function ($facture){
            $year = date('Y');
            $lastFacture = self::whereYear('created_at', $year)->latest('id')->first();
            $lastNumber = $lastFacture ? intval(substr($lastFacture->numero_facture, -3)) : 0;
            $nextNumber = str_pad($lastNumber +1, 3, '0', STR_PAD_LEFT);

            $facture->numero_facture = "FAC-{$year}-{$nextNumber}";
        });
    }

     /**
     * Valide qu'une facture a exactement une relation (achat OU vente)
     */
    private static function validateFactureLogic($facture)
    {
        $hasAchat = !is_null($facture->achat_id);
        $hasVente = !is_null($facture->vente_id);
        
        // Les deux sont remplis
        if ($hasAchat && $hasVente) {
            throw new \InvalidArgumentException('Une facture ne peut pas avoir à la fois un achat_id et un vente_id');
        }
        
        // Aucun des deux n'est rempli
        if (!$hasAchat && !$hasVente) {
            throw new \InvalidArgumentException('Une facture doit avoir soit un achat_id soit un vente_id');
        }
    }

    public function getResume(): array{
        return[
            'id'=>$this->id,
            'achat_id'=> $this->achat_id,
            'vente_id'=> $this->vente_id,
            'numero_facture'=> $this->numero_facture,
            'description'=> $this->description,
            'montant_total' => $this->getMontantTotal(),
            'client' => $this->getClient(),
            'type' => $this->isVenteFacture() ? 'vente' : 'achat',
            'created_by'=>$this->created_by,
            'created_at' => $this->created_at?->format('d/m/Y H:i')
        ];
    }
}
