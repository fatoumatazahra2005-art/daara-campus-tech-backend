<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Jointure propositionsChoisies. */
class ReponseProposition extends Pivot
{
    protected $table = 'reponse_proposition';

    public $incrementing = false;

    protected $fillable = [
        'reponse_id',
        'proposition_id',
    ];

    public function reponse(): BelongsTo
    {
        return $this->belongsTo(ReponseApprenant::class, 'reponse_id');
    }

    public function proposition(): BelongsTo
    {
        return $this->belongsTo(Proposition::class, 'proposition_id');
    }
}
