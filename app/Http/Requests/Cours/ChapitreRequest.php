<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

class ChapitreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'titre' => [$requis, 'string', 'max:150'],
            'objectif' => ['nullable', 'string'],
            'prerequis' => ['nullable', 'string'],
            'duree_estimee' => ['nullable', 'integer', 'min:0'],
            'xp_recompense' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
