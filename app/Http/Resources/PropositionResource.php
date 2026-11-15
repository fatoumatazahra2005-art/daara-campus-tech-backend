<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Proposition */
class PropositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'texte' => $this->texte,
            'est_correcte' => $this->est_correcte,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
        ];
    }
}
