<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BlocContenu */
class BlocResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_bloc' => $this->type_bloc,
            'ordre' => $this->ordre,
            // les champs propres au type du bloc
            'contenu' => $this->when($this->type_bloc === 'TEXTE', fn () => $this->texte?->contenu),
            'url_image' => $this->when($this->type_bloc === 'IMAGE', fn () => $this->image?->url_image),
            'alt' => $this->when($this->type_bloc === 'IMAGE', fn () => $this->image?->alt),
            'code' => $this->when($this->type_bloc === 'CODE', fn () => $this->code?->code),
            'langage' => $this->when($this->type_bloc === 'CODE', fn () => $this->code?->langage),
        ];
    }
}
