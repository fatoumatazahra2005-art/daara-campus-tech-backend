<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table blocs_image. */
class BlocImage extends Model
{
    protected $table = 'blocs_image';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'url_image',
        'alt',
    ];

    public function bloc(): BelongsTo
    {
        return $this->belongsTo(BlocContenu::class, 'id');
    }
}
