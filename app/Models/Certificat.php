<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table certificats. */
class Certificat extends Model
{
    protected $table = 'certificats';

    protected $fillable = [
        'date_obtention',
        'code_verification',
        'nom_apprenant',
        'titre_cours',
        'url_pdf',
        'inscription_cours_id',
    ];

    protected $casts = [
        'date_obtention' => 'date',
    ];

    public function inscriptionCours(): BelongsTo
    {
        return $this->belongsTo(InscriptionCours::class, 'inscription_cours_id');
    }
}
