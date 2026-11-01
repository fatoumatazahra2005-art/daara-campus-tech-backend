<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Jointure viaParcours. */
class InscriptionParcoursCours extends Pivot
{
    protected $table = 'inscription_parcours_cours';

    public $incrementing = false;

    protected $fillable = [
        'inscription_parcours_id',
        'inscription_cours_id',
    ];

    public function inscriptionParcours(): BelongsTo
    {
        return $this->belongsTo(InscriptionParcours::class, 'inscription_parcours_id');
    }

    public function inscriptionCours(): BelongsTo
    {
        return $this->belongsTo(InscriptionCours::class, 'inscription_cours_id');
    }
}
