<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Création (POST) et modification (PUT) d'un cours. */
class CoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // droits contrôlés par le middleware de rôle et CoursPolicy
    }

    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'titre' => [$requis, 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'niveau' => [$requis, Rule::exists('code_lists', 'value')->where('type', 'NiveauApprenant')->where('active', true)],
            'langue' => ['sometimes', 'string', 'max:10'],
            'est_certifiant' => ['sometimes', 'boolean'],
            'image_couverture' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
            'technologie_id' => [$requis, 'integer', 'exists:technologies,id'],
        ];
    }
}
