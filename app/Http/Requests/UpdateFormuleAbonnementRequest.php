<?php

namespace App\Http\Requests;

use App\Models\FormuleAbonnement;

class UpdateFormuleAbonnementRequest extends StoreFormuleAbonnementRequest
{
    public function rules(): array
    {
        // {formule} est lié au modèle FormuleAbonnement dans le contrôleur.
        $id = $this->route('formule')?->id;

        return [
            'libelle'                => ['sometimes', 'required', 'string', 'max:100', $this->libelleUnique($id)],
            'description'            => ['sometimes', 'nullable', 'string', 'max:1000'],
            'periodicite_id'         => ['sometimes', 'required', 'integer', $this->periodiciteValide()],
            'prix'                   => ['sometimes', 'required', 'numeric', 'min:0', 'max:10000000'],
            'nb_echeances_max'       => ['sometimes', 'required', 'integer', 'min:1', 'max:3'],
            'quota_hors_ligne'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000'],
            'jours_essai'            => ['sometimes', 'nullable', 'integer', 'min:0', 'max:90'],
            'limite_chapitres_essai' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'active'                 => ['sometimes', 'boolean'],
        ];
    }

    /** Les champs non envoyés reprennent la valeur actuelle de la formule. */
    protected function formuleActuelle(): ?FormuleAbonnement
    {
        $formule = $this->route('formule');

        return $formule instanceof FormuleAbonnement ? $formule : null;
    }
}
