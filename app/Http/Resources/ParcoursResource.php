<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Parcours */
class ParcoursResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'image' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'statut' => $this->whenLoaded('statut', fn () => $this->statut?->value),
            'date_creation' => $this->date_creation?->toDateString(),

            'cours_count' => $this->whenCounted('cours'),
            'projets_count' => $this->whenCounted('projets'),
            'inscriptions_count' => $this->whenCounted('inscriptions'),

            // Cours dans l'ordre du parcours, avec les données du pivot.
            'cours' => $this->whenLoaded('cours', fn () => $this->cours->map(fn ($cours) => [
                'id' => $cours->id,
                'titre' => $cours->titre,
                'image_couverture' => $cours->image_couverture
                    ? Storage::disk('public')->url($cours->image_couverture)
                    : null,
                'statut' => $cours->relationLoaded('statut') ? $cours->statut?->value : null,
                'niveau' => $cours->relationLoaded('niveau') ? $cours->niveau?->value : null,
                'technologie' => $cours->relationLoaded('technologie') && $cours->technologie
                    ? [
                        'id' => $cours->technologie->id,
                        'nom' => $cours->technologie->nom,
                        'icone' => $cours->technologie->icone,
                    ]
                    : null,
                'ordre' => (int) $cours->pivot->ordre,
                'obligatoire' => (bool) $cours->pivot->obligatoire,
            ])->values()),

            // Technologies du parcours, sans doublon, dans l'ordre des cours (logos des cartes).
            'technologies' => $this->whenLoaded('cours', fn () => $this->cours
                ->sortBy(fn ($cours) => (int) $cours->pivot->ordre)
                ->filter(fn ($cours) => $cours->relationLoaded('technologie') && $cours->technologie)
                ->map(fn ($cours) => [
                    'id' => $cours->technologie->id,
                    'nom' => $cours->technologie->nom,
                    'icone' => $cours->technologie->icone,
                ])
                ->unique('id')
                ->values()),

            'projets' => $this->whenLoaded('projets', fn () => $this->projets->map(fn ($projet) => [
                'id' => $projet->id,
                'titre' => $projet->titre,
                'consigne' => $projet->consigne,
                'delai_jours' => (int) $projet->delai_jours,
                'note_minimale' => $projet->note_minimale !== null ? (float) $projet->note_minimale : null,
                'rendus_count' => (int) ($projet->rendus_count ?? 0),
            ])->values()),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
