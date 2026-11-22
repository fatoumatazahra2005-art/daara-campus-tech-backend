<?php

namespace App\Http\Requests;

use App\Rules\TelephoneSenegal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUtilisateurRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // StoreUtilisateurRequest
    public function authorize(): bool { return true; }   // la Policy s'en charge
    public function rules(): array
    {
        return [
            'role'       => ['required', 'in:ADMIN,ENCADREUR'],
            'nom'        => ['required', 'string', 'max:100'],
            'prenom'     => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:utilisateurs,email'],
            'telephone' => ['nullable', 'string', 'max:30', new TelephoneSenegal()],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
            'specialite' => ['required_if:role,ENCADREUR', 'nullable', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.confirmed' => 'Les deux mots de passe ne correspondent pas.',
            'specialite.required_if' => 'La spécialité est obligatoire pour un encadreur.',
        ];
    }
}
