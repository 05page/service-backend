<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class EmployeIntermediaire extends Model
{
    //
    protected $fillable = [
        'type',
        'nom_complet',
        'email',
        'telephone',
        'adresse',
        'code_activation',
        'activated_at',
        'permissions',
        'taux_commission',
        'active',
        'created_by'
    ];

    protected $casts = [
        'permissions' => 'array',
        'activated_at' => 'datetime',
        'taux_commission' => 'decimal:2',
        'active' => 'boolean'
    ];

    const TYPE_EMPLOYE = 'employe';
    const TYPE_INTERMEDIAIRE = 'intermediaire';

    protected static function boot(){
        parent::boot();
        static::creating(function($employeIntermediaire){
            $employeIntermediaire->code_activation = Str::random(8);
        });
    }
}
