<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table blocs_texte. */
class BlocTexte extends Model
{
    protected $table = 'blocs_texte';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'contenu',
    ];

    public function bloc(): BelongsTo
    {
        return $this->belongsTo(BlocContenu::class, 'id');
    }
}
