<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Profil encadreur : auteur de cours et correcteur de rendus. */
class Encadreur extends Model
{
    protected $table = 'encadreurs';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'specialite',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'id');
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class, 'auteur_id');
    }

    public function rendusACorriger(): HasMany
    {
        return $this->hasMany(RenduProjet::class, 'correcteur_id');
    }
}
