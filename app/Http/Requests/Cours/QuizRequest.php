<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuizRequest extends FormRequest
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
            'duree_limite' => ['nullable', 'integer', 'min:1'],
            'mode' => [$requis, Rule::exists('code_lists', 'value')->where('type', 'ModeQuiz')->where('active', true)],
            'seuil_reussite' => ['sometimes', 'numeric', 'between:0,100'],
            'xp_bonus' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
