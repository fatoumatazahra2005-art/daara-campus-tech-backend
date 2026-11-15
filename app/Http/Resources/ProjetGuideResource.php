<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProjetGuide */
class ProjetGuideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'image_resultat' => $this->image_resultat,
            'etapes' => EtapeResource::collection($this->whenLoaded('etapes')),
        ];
    }
}
