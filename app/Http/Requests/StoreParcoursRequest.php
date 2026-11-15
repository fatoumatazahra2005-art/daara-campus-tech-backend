<?php

namespace App\Http\Requests;

use App\Models\Parcours;
use Illuminate\Foundation\Http\FormRequest;

class StoreParcoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Espaces superflus retirés avant validation. */
    protected function prepareForValidation(): void
    {
        if ($this->has('titre')) {
            $this->merge([
                'titre' => preg_replace('/\s+/', ' ', trim((string) $this->input('titre'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'titre' => [
                'required',
                'string',
                'max:150',
                $this->titreUnique(),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Le titre du parcours est obligatoire.',
            'titre.max' => 'Le titre ne doit pas dépasser 150 caractères.',
            'image.image' => 'Le fichier doit être une image.',
            'image.mimes' => 'L’image doit être au format JPEG, PNG ou WEBP.',
            'image.max' => 'L’image ne doit pas dépasser 2 Mo.',
        ];
    }

    /** Titre unique, insensible à la casse et aux espaces. */
    protected function titreUnique(?int $ignorerId = null): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec) use ($ignorerId) {
            $existe = Parcours::query()
                ->whereRaw('LOWER(TRIM(titre)) = ?', [mb_strtolower(trim((string) $valeur))])
                ->when($ignorerId, fn ($q) => $q->whereKeyNot($ignorerId))
                ->first();

            if ($existe) {
                $echec("Un parcours « {$existe->titre} » existe déjà.");
            }
        };
    }
}
