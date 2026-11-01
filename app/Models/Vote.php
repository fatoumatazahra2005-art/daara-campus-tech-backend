<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Clé primaire composée (utilisateur_id, message_id) : à créer et supprimer par les relations de MessageForum ou par requête, pas par save() sur un modèle existant. */
class Vote extends Model
{
    protected $table = 'votes';

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'utilisateur_id',
        'message_id',
        'date_vote',
    ];

    protected $casts = [
        'date_vote' => 'datetime',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(MessageForum::class, 'message_id');
    }
}
