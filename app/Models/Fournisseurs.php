<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fournisseurs extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_fournisseurs',
        'email',
        'telephone',
        'adresse',
        'services',
        'created_by',
        'actif'
    ];

    protected $casts = [
        'actif' => 'boolean',
        'services'=> 'array'
    ];

    /**
     * Relation polymorphe avec celui qui a créé le fournisseur
     * Peut être un User (admin) ou un EmployeIntermediaire (employé)
     */
    public function creePar()
    {
       return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function isActif(): bool
    {
        return $this->actif;
    }


    /**
     * Désactiver le fournisseur
     */
    public function desactiver(): bool
    {
        $this->actif = false;
        return $this->save();
    }

    /**
     * Réactiver le fournisseur
     */
    public function reactiver(): bool
    {
        $this->actif = true;
        return $this->save();
    }

    public function getServicesTextAttribute(): string
    {
        if (!$this->services || !is_array($this->services)) {
            return '';
        }
        return implode(', ', $this->services);
    }
}