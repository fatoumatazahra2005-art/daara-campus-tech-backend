<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table telechargements. */
class Telechargement extends Model
{
    protected $table = 'telechargements';

    protected $fillable = [
        'date_telechargement',
        'date_derniere_verification',
        'chapitre_id',
        'abonnement_id',
    ];

    protected $casts = [
        'date_telechargement' => 'datetime',
        'date_derniere_verification' => 'datetime',
    ];

    public function chapitre(): BelongsTo
    {
        return $this->belongsTo(Chapitre::class, 'chapitre_id');
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id');
    }
}
