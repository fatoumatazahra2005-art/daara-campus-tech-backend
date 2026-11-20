<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Projet */
class ProjetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'consigne' => $this->consigne,
            'delai_jours' => (int) $this->delai_jours,
            'note_minimale' => $this->note_minimale !== null ? (float) $this->note_minimale : null,
            // Règle 29 : un projet inactif n'est plus attribué ni exigé (ses rendus sont conservés).
            'actif' => (bool) ($this->actif ?? true),
            'parcours_id' => $this->parcours_id,
            'parcours' => $this->whenLoaded('parcours', fn () => [
                'id' => $this->parcours->id,
                'titre' => $this->parcours->titre,
                'statut' => $this->parcours->relationLoaded('statut') ? $this->parcours->statut?->value : null,
            ]),
            'rendus_count' => $this->whenCounted('rendus'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
