<?php

namespace App\Http\Resources\Technologie;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnologieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'icone' => $this->icone,
            'actif' => (bool) $this->actif,
            'description' => $this->description,
            'cours_count' => $this->whenCounted('cours'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
