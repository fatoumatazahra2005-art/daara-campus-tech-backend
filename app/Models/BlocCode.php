<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table blocs_code. */
class BlocCode extends Model
{
    protected $table = 'blocs_code';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'langage',
    ];

    public function bloc(): BelongsTo
    {
        return $this->belongsTo(BlocContenu::class, 'id');
    }
}
