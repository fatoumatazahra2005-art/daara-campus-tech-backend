<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table formules_abonnement. */
class FormuleAbonnement extends Model
{
    protected $table = 'formules_abonnement';

    protected $fillable = [
        'libelle',
        'description',
        'periodicite_id',
        'prix',
        'nb_echeances_max',
        'quota_hors_ligne',
        'jours_essai',
        'limite_chapitres_essai',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function periodicite(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'periodicite_id');
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'formule_id');
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(PeriodeAbonnement::class, 'formule_id');
    }
}
