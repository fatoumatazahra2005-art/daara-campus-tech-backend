<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Table reponses_apprenant. */
class ReponseApprenant extends Model
{
    protected $table = 'reponses_apprenant';

    protected $fillable = [
        'date_reponse',
        'tentative_id',
        'question_id',
    ];

    protected $casts = [
        'date_reponse' => 'datetime',
    ];

    public function tentative(): BelongsTo
    {
        return $this->belongsTo(TentativeQuiz::class, 'tentative_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    public function propositionsChoisies(): BelongsToMany
    {
        return $this->belongsToMany(Proposition::class, 'reponse_proposition', 'reponse_id', 'proposition_id')->using(ReponseProposition::class)->withTimestamps();
    }
}
