<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue de l'auteur et de l'administrateur : les bonnes réponses (est_correcte) sont visibles.
 * Ne jamais l'utiliser pour servir un quiz à un apprenant.
 *
 * @mixin \App\Models\Question
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'enonce' => $this->enonce,
            'explication' => $this->explication,
            'type' => $this->whenLoaded('type', fn () => $this->type->value),
            'points' => $this->points,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'propositions' => PropositionResource::collection($this->whenLoaded('propositions')),
        ];
    }
}
