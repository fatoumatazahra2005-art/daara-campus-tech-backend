<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Parcours créé et géré par un administrateur. */
class Parcours extends Model
{
    protected $table = 'parcours';

    protected $fillable = [
        'titre',
        'description',
        'statut_id',
        'date_creation',
    ];

    protected $casts = [
        'date_creation' => 'date',
    ];

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function cours(): BelongsToMany
    {
        return $this->belongsToMany(Cours::class, 'parcours_cours', 'parcours_id', 'cours_id')->using(ParcoursCours::class)->withPivot(['ordre', 'obligatoire'])->withTimestamps()->orderBy('parcours_cours.ordre');
    }

    public function projets(): HasMany
    {
        return $this->hasMany(Projet::class, 'parcours_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(InscriptionParcours::class, 'parcours_id');
    }
}
