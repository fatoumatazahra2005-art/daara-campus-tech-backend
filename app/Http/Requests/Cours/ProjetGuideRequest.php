<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

class ProjetGuideRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'image_resultat' => ['nullable', 'string', 'max:500'],
        ];
    }
}
