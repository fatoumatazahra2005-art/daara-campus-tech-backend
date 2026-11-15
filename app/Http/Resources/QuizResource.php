<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quiz */
class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'duree_limite' => $this->duree_limite,
            'mode' => $this->whenLoaded('mode', fn () => $this->mode->value),
            'seuil_reussite' => $this->seuil_reussite,
            'xp_bonus' => $this->xp_bonus,
            'actif' => $this->actif,
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'chapitre_id' => $this->chapitre_id,
        ];
    }
}
