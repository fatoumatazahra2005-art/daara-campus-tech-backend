<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\EtapeProjetGuide */
class EtapeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ordre' => $this->ordre,
            'consigne' => $this->consigne,
            'code_depart' => $this->code_depart,
            'tests_attendus' => $this->tests_attendus,
            'chapitre_id' => $this->chapitre_id,
        ];
    }
}
