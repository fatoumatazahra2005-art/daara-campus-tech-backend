<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Valeur d'une liste du référentiel (type + value). Les colonnes *_id des autres tables pointent ici. */
class CodeList extends Model
{
    protected $table = 'code_lists';

    protected $fillable = [
        'type',
        'value',
        'description',
        'ordre',
        'active',
        'est_systeme',
        'date_creation',
    ];

    protected $casts = [
        'active' => 'boolean',
        'est_systeme' => 'boolean',
        'date_creation' => 'date',
    ];

    /** @var array<string, int> cache mémoire « type.value » => id, le temps d'une requête */
    private static array $ids = [];

    public function scopeDuType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Identifiant d'une valeur : CodeList::idOf('StatutCours', 'BROUILLON'). */
    public static function idOf(string $type, string $value): int
    {
        return self::$ids["{$type}.{$value}"] ??= (int) static::query()
            ->where('type', $type)
            ->where('value', $value)
            ->valueOrFail('id');
    }
}
