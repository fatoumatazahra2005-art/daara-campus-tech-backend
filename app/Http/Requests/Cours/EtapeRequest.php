<?php

namespace App\Http\Requests\Cours;

use Illuminate\Foundation\Http\FormRequest;

class EtapeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requis = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'consigne' => [$requis, 'string'],
            'code_depart' => ['nullable', 'string'],
            'tests_attendus' => ['nullable', 'string'],
            'chapitre_id' => [$requis, 'integer', 'exists:chapitres,id'],
        ];
    }
}
