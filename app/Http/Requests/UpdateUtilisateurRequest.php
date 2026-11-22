<?php

namespace App\Http\Requests;

use App\Rules\TelephoneSenegal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUtilisateurRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    // UpdateUtilisateurRequest
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'nom'        => ['required', 'string', 'max:100'],
            'prenom'     => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255',
                Rule::unique('utilisateurs', 'email')->ignore($this->route('utilisateur')->id)],
            'telephone' => ['nullable', 'string', 'max:30', new TelephoneSenegal()],
            'specialite' => ['nullable', 'string', 'max:150'],
        ];
    }
}
