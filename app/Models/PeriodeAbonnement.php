<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table periodes_abonnement. */
class PeriodeAbonnement extends Model
{
    protected $table = 'periodes_abonnement';

    protected $fillable = [
        'numero',
        'date_debut',
        'date_fin',
        'prix_paye',
        'nb_echeances',
        'est_essai',
        'abonnement_id',
        'formule_id',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'est_essai' => 'boolean',
    ];

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id');
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(FormuleAbonnement::class, 'formule_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class, 'periode_id')->orderBy('numero_echeance');
    }
}
