<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModifierCoursParcoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ordre'       => ['sometimes', 'integer', 'min:1'],
            'obligatoire' => ['sometimes', 'boolean'],
        ];
    }
}
