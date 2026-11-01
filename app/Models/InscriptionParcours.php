<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Table inscriptions_parcours. */
class InscriptionParcours extends Model
{
    protected $table = 'inscriptions_parcours';

    protected $fillable = [
        'date_inscription',
        'progression',
        'statut_id',
        'apprenant_id',
        'parcours_id',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'progression' => 'decimal:2',
    ];

    public function apprenant(): BelongsTo
    {
        return $this->belongsTo(Apprenant::class, 'apprenant_id');
    }

    public function parcours(): BelongsTo
    {
        return $this->belongsTo(Parcours::class, 'parcours_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function inscriptionsCours(): BelongsToMany
    {
        return $this->belongsToMany(InscriptionCours::class, 'inscription_parcours_cours', 'inscription_parcours_id', 'inscription_cours_id')->using(InscriptionParcoursCours::class)->withTimestamps();
    }
}
