<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table rendus_projet. */
class RenduProjet extends Model
{
    protected $table = 'rendus_projet';

    protected $fillable = [
        'date_attribution',
        'date_limite',
        'date_rendu',
        'lien_github',
        'statut_id',
        'note',
        'commentaire',
        'projet_id',
        'apprenant_id',
        'correcteur_id',
    ];

    protected $casts = [
        'date_attribution' => 'date',
        'date_limite' => 'date',
        'date_rendu' => 'datetime',
        'note' => 'decimal:2',
    ];

    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'projet_id');
    }

    public function apprenant(): BelongsTo
    {
        return $this->belongsTo(Apprenant::class, 'apprenant_id');
    }

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(Encadreur::class, 'correcteur_id');
    }

    public function statut(): BelongsTo
    {
        return $this->belongsTo(CodeList::class, 'statut_id');
    }
}
