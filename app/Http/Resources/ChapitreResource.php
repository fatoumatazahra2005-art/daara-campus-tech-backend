<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Chapitre */
class ChapitreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'objectif' => $this->objectif,
            'prerequis' => $this->prerequis,
            'ordre' => $this->ordre,
            'duree_estimee' => $this->duree_estimee,
            'xp_recompense' => $this->xp_recompense,
            'actif' => $this->actif,
            'blocs' => BlocResource::collection($this->whenLoaded('blocs')),
            'quiz' => $this->whenLoaded('quiz', fn () => $this->quiz ? new QuizResource($this->quiz) : null),
        ];
    }
}
