<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Bloc abstrait d'un chapitre ; type_bloc vaut TEXTE, IMAGE ou CODE. */
class BlocContenu extends Model
{
    protected $table = 'blocs_contenu';

    protected $fillable = [
        'ordre',
        'type_bloc',
        'chapitre_id',
    ];

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class, 'chapitre_id');
    }

    public function texte(): HasOne
    {
        return $this->hasOne(BlocTexte::class, 'id');
    }

    public function image(): HasOne
    {
        return $this->hasOne(BlocImage::class, 'id');
    }

    public function code(): HasOne
    {
        return $this->hasOne(BlocCode::class, 'id');
    }
}
