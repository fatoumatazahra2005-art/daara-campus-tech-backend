<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Cours créé par un encadreur, validé par un administrateur. Le nom de table « cours » est invariable. */
class Cours extends Model
{
    protected $table = 'cours';

    protected $fillable = [
        'titre',
        'description',
        'niveau_id',
        'langue',
        'est_certifiant',
        'statut_id',
        'motif_refus',
        'date_creation',
        'date_publication',
        'auteur_id',
        'technologie_id',
        'valide_par_id',
    ];

    protected $casts = [
        'est_certifiant' => 'boolean',
        'date_creation' => 'date',
        'date_publication' => 'date',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Encadreur::class, 'auteur_id');
    }

    public function technologie(): BelongsTo
    {
        return $this->belongsTo(Technologie::class, 'technologie_id');
    }

    public function validePar(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'valide_par_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'niveau_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'cours_id')->orderBy('ordre');
    }

    public function projetsGuides(): HasMany
    {
        return $this->hasMany(ProjetGuide::class, 'cours_id');
    }

    public function parcours(): BelongsToMany
    {
        return $this->belongsToMany(Parcours::class, 'parcours_cours', 'cours_id', 'parcours_id')->using(ParcoursCours::class)->withPivot(['ordre', 'obligatoire'])->withTimestamps();
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(InscriptionCours::class, 'cours_id');
    }

    public function chapitres(): HasManyThrough
    {
        return $this->hasManyThrough(Chapitre::class, Module::class, 'cours_id', 'module_id');
    }
}
