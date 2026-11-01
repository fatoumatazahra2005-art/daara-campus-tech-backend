<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table signalements. */
class Signalement extends Model
{
    protected $table = 'signalements';

    protected $fillable = [
        'motif',
        'date_signalement',
        'traite',
        'utilisateur_id',
        'message_id',
    ];

    protected $casts = [
        'date_signalement' => 'datetime',
        'traite' => 'boolean',
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
