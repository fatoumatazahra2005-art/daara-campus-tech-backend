<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table projets. */
class Projet extends Model
{
    protected $table = 'projets';

    protected $fillable = [
        'titre',
        'consigne',
        'delai_jours',
        'note_minimale',
        'parcours_id',
    ];

    protected $casts = [
        'note_minimale' => 'decimal:2',
    ];

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(Parcours::class, 'parcours_id');
    }

    public function rendus(): HasMany
    {
        return $this->hasMany(RenduProjet::class, 'projet_id');
    }
}
