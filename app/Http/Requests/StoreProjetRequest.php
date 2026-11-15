<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('titre')) {
            $this->merge(['titre' => preg_replace('/\s+/', ' ', trim((string) $this->input('titre')))]);
        }
    }

    public function rules(): array
    {
        return [
            'titre'         => ['required', 'string', 'max:150'],
            'consigne'      => ['required', 'string', 'min:20'],
            'delai_jours'   => ['required', 'integer', 'min:1', 'max:365'],
            'note_minimale' => ['required', 'numeric', 'min:0', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre du projet est obligatoire.',
            'consigne.required' => 'La consigne est obligatoire.',
            'consigne.min' => 'La consigne doit être assez détaillée (20 caractères minimum).',
            'delai_jours.required' => 'Le délai est obligatoire.',
            'delai_jours.min' => 'Le délai doit être d\'au moins 1 jour.',
            'delai_jours.max' => 'Le délai ne peut pas dépasser 365 jours.',
            'note_minimale.required' => 'La note minimale est obligatoire.',
            'note_minimale.max' => 'La note minimale ne peut pas dépasser 20.',
        ];
    }
}
