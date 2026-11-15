<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],

            'prenom' => ['required', 'string', 'max:100'],

            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                'unique:utilisateurs,email',
            ],

            'telephone' => [
                'required',
                'string',
                'max:20',
                'unique:utilisateurs,telephone',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }
}
