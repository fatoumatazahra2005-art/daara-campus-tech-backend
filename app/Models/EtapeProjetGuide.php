<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table etapes_projet_guide. */
class EtapeProjetGuide extends Model
{
    protected $table = 'etapes_projet_guide';

    protected $fillable = [
        'ordre',
        'consigne',
        'code_depart',
        'tests_attendus',
        'projet_guide_id',
        'chapitre_id',
    ];

    public function projetGuide(): BelongsTo
    {
        return $this->belongsTo(ProjetGuide::class, 'projet_guide_id');
    }

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class, 'chapitre_id');
    }
}
