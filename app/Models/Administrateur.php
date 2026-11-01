<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Profil administrateur. */
class Administrateur extends Model
{
    protected $table = 'administrateurs';

    public $incrementing = false;

    protected $fillable = [
        'id',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'id');
    }

    public function coursValides(): HasMany
    {
        return $this->hasMany(Cours::class, 'valide_par_id');
    }

    public function messagesMasques(): HasMany
    {
        return $this->hasMany(MessageForum::class, 'masque_par_id');
    }
}
