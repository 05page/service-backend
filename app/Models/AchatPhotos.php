<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchatPhotos extends Model
{
    // ✅ Ajouter achat_item_id dans fillable
    protected $fillable = ['achat_id', 'achat_item_id', 'path'];

    // Relation avec Achats (achat principal)
    public function achat()
    {
        return $this->belongsTo(Achats::class, 'achat_id');
    }

    // ✅ Relation avec AchatItems (item spécifique)
    public function achatItem()
    {
        return $this->belongsTo(AchatItems::class, 'achat_item_id');
    }
}