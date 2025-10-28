<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AchatPhotos extends Model
{
    //
    protected $fillable = ['achat_id', 'path'];

    public function achat()
    {
        return $this->belongsTo(Achats::class, 'achat_id');
    }
}
