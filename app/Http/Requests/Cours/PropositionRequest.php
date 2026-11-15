<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

class PropositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'texte' => [$requis, 'string', 'max:500'],
            'est_correcte' => ['sometimes', 'boolean'],
        ];
    }
}
