<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Cours */
class CourResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'niveau' => $this->whenLoaded('niveau', fn () => $this->niveau->value),
            'langue' => $this->langue,
            'est_certifiant' => $this->est_certifiant,
            'image_couverture' => $this->image_couverture
                ? Storage::disk('public')->url($this->image_couverture)
                : null,
            'statut' => $this->whenLoaded('statut', fn () => $this->statut->value),
            'motif_refus' => $this->motif_refus,
            'a_relire' => (bool) $this->a_relire,
            'modifie_le' => $this->modifie_le?->format('Y-m-d H:i:s'),
            'date_creation' => $this->date_creation?->toDateString(),
            'date_publication' => $this->date_publication?->toDateString(),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            // Technologie du cours, avec son icône (URL saisie par l'admin, sinon logo trouvé d'après le nom)
            'technologie' => $this->whenLoaded('technologie', fn () => $this->technologie ? [
                'id' => $this->technologie->id,
                'nom' => $this->technologie->nom,
                'icone' => $this->technologie->icone,
            ] : null),
            'auteur' => $this->whenLoaded('auteur', fn () => [
                'id' => $this->auteur->id,
                'nom' => trim($this->auteur->utilisateur?->prenom . ' ' . $this->auteur->utilisateur?->nom),
            ]),
            'modules' => ModuleResource::collection($this->whenLoaded('modules')),
            'projets_guides' => ProjetGuideResource::collection($this->whenLoaded('projetsGuides')),
        ];
    }
}
