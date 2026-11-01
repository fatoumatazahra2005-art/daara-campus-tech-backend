<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table tentatives_quiz. */
class TentativeQuiz extends Model
{
    protected $table = 'tentatives_quiz';

    protected $fillable = [
        'date_debut',
        'date_fin',
        'score',
        'statut_id',
        'apprenant_id',
        'quiz_id',
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin' => 'datetime',
        'score' => 'decimal:2',
    ];

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function apprenant(): BelongsTo
    {
        return $this->belongsTo(Apprenant::class, 'apprenant_id');
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseApprenant::class, 'tentative_id');
    }
}
