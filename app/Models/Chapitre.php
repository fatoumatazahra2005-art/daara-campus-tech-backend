<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table chapitres. */
class Chapitre extends Model
{
    protected $table = 'chapitres';

    protected $fillable = [
        'titre',
        'objectif',
        'prerequis',
        'ordre',
        'duree_estimee',
        'xp_recompense',
        'actif',
        'module_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function blocs(): HasMany
    {
        return $this->hasMany(BlocContenu::class, 'chapitre_id')->orderBy('ordre');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class, 'chapitre_id');
    }

    public function progressions(): HasMany
    {
        return $this->hasMany(ProgressionChapitre::class, 'chapitre_id');
    }

    public function etapesProjetGuide(): HasMany
    {
        return $this->hasMany(EtapeProjetGuide::class, 'chapitre_id');
    }

    public function telechargements(): HasMany
    {
        return $this->hasMany(Telechargement::class, 'chapitre_id');
    }
}
