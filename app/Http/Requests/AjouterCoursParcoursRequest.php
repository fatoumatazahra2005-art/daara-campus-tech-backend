<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjouterCoursParcoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cours'               => ['required', 'array', 'min:1'],
            'cours.*.cours_id'    => ['required', 'integer', 'exists:cours,id'],
            'cours.*.ordre'       => ['nullable', 'integer', 'min:1'],
            'cours.*.obligatoire' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cours.required' => 'Sélectionnez au moins un cours.',
            'cours.*.cours_id.exists' => 'Un des cours sélectionnés n\'existe pas.',
        ];
    }
}
