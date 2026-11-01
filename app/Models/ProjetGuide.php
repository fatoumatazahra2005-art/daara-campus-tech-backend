<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table projets_guides. */
class ProjetGuide extends Model
{
    protected $table = 'projets_guides';

    protected $fillable = [
        'titre',
        'description',
        'image_resultat',
        'cours_id',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function etapes(): HasMany
    {
        return $this->hasMany(EtapeProjetGuide::class, 'projet_guide_id')->orderBy('ordre');
    }
}
