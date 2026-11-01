<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Profil apprenant (XP, niveau, série de jours, essai gratuit). */
class Apprenant extends Model
{
    protected $table = 'apprenants';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'xp_total',
        'niveau_xp',
        'serie_jours_consecutifs',
        'niveau_id',
        'date_derniere_activite',
        'essai_utilise',
    ];

    protected $casts = [
        'date_derniere_activite' => 'date',
        'essai_utilise' => 'boolean',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'niveau_id');
    }

    public function inscriptionsCours(): HasMany
    {
        return $this->hasMany(InscriptionCours::class, 'apprenant_id');
    }

    public function inscriptionsParcours(): HasMany
    {
        return $this->hasMany(InscriptionParcours::class, 'apprenant_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'apprenant_id');
    }

    public function tentatives(): HasMany
    {
        return $this->hasMany(TentativeQuiz::class, 'apprenant_id');
    }

    public function rendus(): HasMany
    {
        return $this->hasMany(RenduProjet::class, 'apprenant_id');
    }
}
