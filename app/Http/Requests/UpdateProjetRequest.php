<?php

namespace App\Http\Requests;

class UpdateProjetRequest extends StoreProjetRequest
{
    public function rules(): array
    {
        return [
            'titre'         => ['sometimes', 'required', 'string', 'max:150'],
            'consigne'      => ['sometimes', 'required', 'string', 'min:20'],
            'delai_jours'   => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'note_minimale' => ['sometimes', 'required', 'numeric', 'min:0', 'max:20'],
        ];
    }
}
