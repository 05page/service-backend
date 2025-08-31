<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Services extends Model
{
    //

    protected $fillable = [
        "fournisseur_id",
        "nom_service",
        "description",
        "prix_service",
        "statut",
        "active",
        "created_by"
    ];

    protected $casts = [
        'active'=> 'boolean'
    ];

    const SERVICE_DISPONIBLE = "disponible";
    const SERVICE_RUPTURE = "rupture";
    const SERVICE_ALERTE = "Alerte";

    public function addBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fournisseurId(): BelongsTo
    {
        return $this->belongsTo(Fournisseurs::class, 'fournisseur_id');
    }

    /** */

    public function scopeStatut($query, $statut)
    {
        return $query->where('statut', $statut);
    }

    public function scopeDisponible($query)
    {
        return $query->where('statut', self::SERVICE_DISPONIBLE);
    }

    public function scopeRupture($query){
        return $query->where('statut', self::SERVICE_RUPTURE);
    }

    public function scopeAlerte($query){
        return $query->where('statut', self::SERVICE_ALERTE);
    }
    
    public function actif(): bool
    {
        return $this->actif;
    }

}
