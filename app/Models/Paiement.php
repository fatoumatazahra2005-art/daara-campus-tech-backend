<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table paiements. */
class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'montant',
        'date_echeance',
        'date_paiement',
        'numero_echeance',
        'methode_id',
        'statut_id',
        'reference',
        'numero_payeur',
        'date_notification',
        'periode_id',
    ];

    protected $casts = [
        'date_echeance' => 'date',
        'date_paiement' => 'datetime',
        'date_notification' => 'datetime',
    ];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAbonnement::class, 'periode_id');
    }

    public function methode(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'methode_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }
}
