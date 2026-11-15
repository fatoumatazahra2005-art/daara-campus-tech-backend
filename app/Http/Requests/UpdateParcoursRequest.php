<?php

namespace App\Http\Requests;

class UpdateParcoursRequest extends StoreParcoursRequest
{
    public function rules(): array
    {
        // La route passe l'identifiant (int $parcours dans le contrôleur).
        $id = (int) $this->route('parcours');

        return [
            'titre' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                $this->titreUnique($id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
