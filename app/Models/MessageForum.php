<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table messages_forum. */
class MessageForum extends Model
{
    protected $table = 'messages_forum';

    protected $fillable = [
        'contenu',
        'date_envoi',
        'nb_votes',
        'nb_signalements',
        'est_masque',
        'date_masquage',
        'sujet_id',
        'auteur_id',
        'masque_par_id',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
        'est_masque' => 'boolean',
        'date_masquage' => 'datetime',
    ];

    public function sujet(): BelongsTo
    {
        return $this->belongsTo(SujetDiscussion::class, 'sujet_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'auteur_id');
    }

    public function masquePar(): BelongsTo
    {
        return $this->belongsTo(Administrateur::class, 'masque_par_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'message_id');
    }

    public function signalements(): HasMany
    {
        return $this->hasMany(Signalement::class, 'message_id');
    }
}
