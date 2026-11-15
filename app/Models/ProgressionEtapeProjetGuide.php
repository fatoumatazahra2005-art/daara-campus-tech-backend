<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressionEtapeProjetGuide extends Model
{
    protected $table = 'progression_etape_projet_guides';

    protected $fillable = [
        'est_terminee',
        'date_validation',
        'inscription_cours_id',
        'etape_projet_guide_id',
    ];

    protected $casts = [
        'est_terminee' => 'boolean',
        'date_validation' => 'datetime',
    ];

    public function inscriptionCours(): BelongsTo
    {
        return $this->belongsTo(
            InscriptionCours::class,
            'inscription_cours_id'
        );
    }

    public function etapeProjetGuide(): BelongsTo
    {
        return $this->belongsTo(
            EtapeProjetGuide::class,
            'etape_projet_guide_id'
        );
    }
}
