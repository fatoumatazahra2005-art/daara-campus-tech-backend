<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table inscriptions_cours. */
class InscriptionCours extends Model
{
    protected $table = 'inscriptions_cours';

    protected $fillable = [
        'date_inscription',
        'progression',
        'statut_id',
        'apprenant_id',
        'cours_id',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'progression' => 'decimal:2',
    ];

    public function apprenant(): BelongsTo
    {
        return $this->belongsTo(Apprenant::class, 'apprenant_id');
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function progressionsChapitres(): HasMany
    {
        return $this->hasMany(ProgressionChapitre::class, 'inscription_cours_id');
    }

    public function certificat(): HasOne
    {
        return $this->hasOne(Certificat::class, 'inscription_cours_id');
    }

    public function inscriptionsParcours(): BelongsToMany
    {
        return $this->belongsToMany(InscriptionParcours::class, 'inscription_parcours_cours', 'inscription_cours_id', 'inscription_parcours_id')->using(InscriptionParcoursCours::class)->withTimestamps();
    }
}
