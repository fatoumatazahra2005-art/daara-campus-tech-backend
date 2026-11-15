<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangerStatutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'autorisation est gérée par la Policy et le middleware role:ADMIN
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:bloquer,debloquer,desactiver,activer'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'L’action est obligatoire.',
            'action.in'       => 'Action invalide : bloquer, debloquer, desactiver ou activer.',
        ];
    }
}
