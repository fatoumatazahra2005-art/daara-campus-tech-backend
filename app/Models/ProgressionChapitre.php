<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table progressions_chapitre. */
class ProgressionChapitre extends Model
{
    protected $table = 'progressions_chapitre';

    protected $fillable = [
        'date_debut',
        'date_fin',
        'est_termine',
        'inscription_cours_id',
        'chapitre_id',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'est_termine' => 'boolean',
    ];

    public function inscriptionCours(): BelongsTo
    {
        return $this->belongsTo(InscriptionCours::class, 'inscription_cours_id');
    }

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class, 'chapitre_id');
    }
}
