<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table questions. */
class Question extends Model
{
    protected $table = 'questions';

    protected $fillable = [
        'enonce',
        'explication',
        'type_id',
        'points',
        'ordre',
        'actif',
        'quiz_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'type_id');
    }

    public function propositions(): HasMany
    {
        return $this->hasMany(Proposition::class, 'question_id')->orderBy('ordre');
    }

    public function reponses(): HasMany
    {
        return $this->hasMany(ReponseApprenant::class, 'question_id');
    }
}
