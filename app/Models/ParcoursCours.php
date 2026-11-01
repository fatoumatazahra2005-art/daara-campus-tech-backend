<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Association parcours / cours (ordre, obligatoire). */
class ParcoursCours extends Pivot
{
    protected $table = 'parcours_cours';

    public $incrementing = false;

    protected $fillable = [
        'parcours_id',
        'cours_id',
        'ordre',
        'obligatoire',
    ];

    protected $casts = [
        'obligatoire' => 'boolean',
    ];

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(Parcours::class, 'parcours_id');
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }
}
