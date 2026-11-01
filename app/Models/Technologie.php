<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table technologies. */
class Technologie extends Model
{
    protected $table = 'technologies';

    protected $fillable = [
        'nom',
        'description',
    ];

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class, 'technologie_id');
    }

    public function sujets(): HasMany
    {
        return $this->hasMany(SujetDiscussion::class, 'technologie_id');
    }
}
