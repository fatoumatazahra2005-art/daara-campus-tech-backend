<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Module */
class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'description' => $this->description,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'chapitres' => ChapitreResource::collection($this->whenLoaded('chapitres')),
        ];
    }
}
