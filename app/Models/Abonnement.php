<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table abonnements. */
class Abonnement extends Model
{
    protected $table = 'abonnements';

    protected $fillable = [
        'statut_id',
        'credits_hors_ligne',
        'date_resiliation',
        'apprenant_id',
        'formule_id',
    ];

    protected $casts = [
        'date_resiliation' => 'date',
    ];

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }

    public function apprenant(): BelongsTo
    {
        return $this->belongsTo(Apprenant::class, 'apprenant_id');
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(FormuleAbonnement::class, 'formule_id');
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(PeriodeAbonnement::class, 'abonnement_id')->orderBy('numero');
    }

    public function telechargements(): HasMany
    {
        return $this->hasMany(Telechargement::class, 'abonnement_id');
    }

    public function dernierePeriode(): HasOne
    {
        return $this->hasOne(PeriodeAbonnement::class, 'abonnement_id')->latestOfMany('numero');
    }
}
