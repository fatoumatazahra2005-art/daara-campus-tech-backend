<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FormuleAbonnement */
class FormuleAbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entier = fn ($v) => $v !== null ? (int) $v : null;

        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'description' => $this->description,
            'periodicite_id' => $this->periodicite_id,
            'periodicite' => $this->whenLoaded('periodicite', fn () => $this->periodicite?->value),
            'duree_mois' => $this->relationLoaded('periodicite') ? $this->dureeMois() : null,
            'prix' => (float) $this->prix,
            'nb_echeances_max' => $entier($this->nb_echeances_max),
            'quota_hors_ligne' => $entier($this->quota_hors_ligne),
            'jours_essai' => $entier($this->jours_essai),
            'limite_chapitres_essai' => $entier($this->limite_chapitres_essai),
            'active' => (bool) $this->active,
            'abonnements_count' => $this->whenCounted('abonnements'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
