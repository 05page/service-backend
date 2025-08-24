<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permissions extends Model
{
    //
    protected $fillable = [
        'employe_id',
        'created_by',
        'description',
        'module',
        'active',
    ];

    protected $casts = [
        'active'=> 'boolean'
    ];

    // Modules disponibles (correspond à votre migration)
    const MODULE_FOURNISSEURS = 'fournisseurs';
    const MODULE_SERVICES = 'services';
    const MODULE_STOCK = 'stock';
    const MODULE_VENTES = 'ventes';
    const MODULE_ACHATS = 'achats';
    const MODULE_FACTURES = 'factures';

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function scopeActive($query){
        return $query->where('active', true);
    }

    public function scopeModule($query, $module){
        return $query->where('module', $module);
    }

    public function scopeEmploye($query, $employeId){
        return $query->where('employe_id', $employeId);
    }

        public static function getModules()
    {
        return [
            self::MODULE_FOURNISSEURS => 'Fournisseurs',
            self::MODULE_SERVICES => 'Services',
            self::MODULE_STOCK => 'Stock',
            self::MODULE_VENTES => 'Ventes',
            self::MODULE_ACHATS => 'Achats',
            self::MODULE_FACTURES => 'Factures'
        ];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function activer(): bool
    {
        return $this->update(['active' => true]);
    }

    public function desactiver(): bool
    {
        return $this->update(['active' => false]);
    }

    public function getResume()
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'module' => self::getModules()[$this->module] ?? $this->module,
            'employe' => $this->employe?->fullname,
            'created_by' => $this->createdBy?->fullname,
            'active' => $this->active,
            'created_at' => $this->created_at->format('d/m/Y H:i')
        ];
    }
}
