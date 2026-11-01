<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table sujets_discussion. */
class SujetDiscussion extends Model
{
    protected $table = 'sujets_discussion';

    protected $fillable = [
        'titre',
        'contenu',
        'date_creation',
        'est_resolu',
        'auteur_id',
        'technologie_id',
    ];

    protected $casts = [
        'date_creation' => 'datetime',
        'est_resolu' => 'boolean',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'auteur_id');
    }

    public function technologie(): BelongsTo
    {
        return $this->belongsTo(Technologie::class, 'technologie_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageForum::class, 'sujet_id');
    }
}
