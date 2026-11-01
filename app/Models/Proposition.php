<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Table propositions. */
class Proposition extends Model
{
    protected $table = 'propositions';

    protected $fillable = [
        'texte',
        'est_correcte',
        'ordre',
        'actif',
        'question_id',
    ];

    protected $casts = [
        'est_correcte' => 'boolean',
        'actif' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function reponses(): BelongsToMany
    {
        return $this->belongsToMany(ReponseApprenant::class, 'reponse_proposition', 'proposition_id', 'reponse_id')->using(ReponseProposition::class)->withTimestamps();
    }
}
