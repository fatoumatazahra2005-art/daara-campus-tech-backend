<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table signalements.
 * Un utilisateur signale au plus une fois un même message (couple unique).
 * Composition de MessageForum : supprimer le message supprime ses signalements.
 */
class Signalement extends Model
{
    protected $table = 'signalements';

    protected $fillable = [
        'motif',
        'date_signalement',
        'traite',
        'utilisateur_id',
        'message_id',
        'traite_par_id',
    ];

    protected $casts = [
        'date_signalement' => 'datetime',
        'traite' => 'boolean',
    ];

    /** Auteur du signalement (signalePar). */
    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    /** Message signalé. */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MessageForum::class, 'message_id');
    }

    /** Administrateur ayant traité le signalement (traitePar) ; vide tant que traite = false. */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'traite_par_id');
    }
}
