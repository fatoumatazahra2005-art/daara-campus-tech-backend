<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table quizzes. */
class Quiz extends Model
{
    protected $table = 'quizzes';

    protected $fillable = [
        'titre',
        'duree_limite',
        'mode_id',
        'seuil_reussite',
        'xp_bonus',
        'actif',
        'date_creation',
        'chapitre_id',
    ];

    protected $casts = [
        'seuil_reussite' => 'decimal:2',
        'actif' => 'boolean',
        'date_creation' => 'date',
    ];

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class, 'chapitre_id');
    }

    public function mode(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'mode_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'quiz_id')->orderBy('ordre');
    }

    public function tentatives(): HasMany
    {
        return $this->hasMany(TentativeQuiz::class, 'quiz_id');
    }
}
