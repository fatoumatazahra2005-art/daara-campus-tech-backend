<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creation = $this->isMethod('POST');
        $requis = $creation ? 'required' : 'sometimes';

        $regles = [
            'enonce' => [$requis, 'string'],
            'explication' => ['nullable', 'string'],
            'type' => [$requis, Rule::exists('code_lists', 'value')->where('type', 'TypeQuestion')->where('active', true)],
            'points' => ['sometimes', 'integer', 'min:1'],
        ];

        // les propositions peuvent être envoyées avec la question à sa création
        if ($creation) {
            $regles['propositions'] = ['sometimes', 'array'];
            $regles['propositions.*.texte'] = ['required', 'string', 'max:500'];
            $regles['propositions.*.est_correcte'] = ['sometimes', 'boolean'];
        }

        return $regles;
    }
}
