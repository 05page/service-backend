<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EmployeIntermediaire extends Model
{
    //
    protected $table = 'employes_intermediaires';
    protected $fillable = [
        'type',
        'nom_complet',
        'email',
        'telephone',
        'adresse',
        'code_activation',
        'activate_at',
        'permissions',
        'taux_commission',
        'active',
        'created_by'
    ];

    protected $casts = [
        'permissions' => 'array',
        'activate_at' => 'datetime',
        'taux_commission' => 'decimal:2',
        'active' => 'boolean'
    ];

    const TYPE_EMPLOYE = 'employe';
    const TYPE_INTERMEDIAIRE = 'intermediaire';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($employeIntermediaire) {
            $employeIntermediaire->code_activation = Str::random(8);
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEmploye(): bool
    {
        return $this->type === self::TYPE_EMPLOYE;
    }

    public function isIntermediaire(): bool
    {
        return $this->type === self::TYPE_INTERMEDIAIRE;
    }

    public function isActive(): bool
    {
        return $this->active; // Vérifie le statut admin
    }

    public function isActivated(): bool
    {
        return !is_null($this->activate_at); // Vérifie l'activation par code
    }

    public function activate(): bool{
        $this->activate_at = now();
        return $this->save();
    }
}
