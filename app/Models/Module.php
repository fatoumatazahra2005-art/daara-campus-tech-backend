<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table modules. */
class Module extends Model
{
    protected $table = 'modules';

    protected $fillable = [
        'titre',
        'description',
        'ordre',
        'actif',
        'cours_id',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function chapitres(): HasMany
    {
        return $this->hasMany(Chapitre::class, 'module_id')->orderBy('ordre');
    }
}
