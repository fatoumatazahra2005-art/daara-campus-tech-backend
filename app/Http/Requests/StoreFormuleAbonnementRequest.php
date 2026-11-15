<?php

namespace App\Http\Requests;

use App\Models\CodeList;
use App\Models\FormuleAbonnement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFormuleAbonnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Libellé nettoyé ; périodicité « MENSUEL » → periodicite_id. */
    protected function prepareForValidation(): void
    {
        if ($this->has('libelle')) {
            $this->merge(['libelle' => preg_replace('/\s+/', ' ', trim((string) $this->input('libelle')))]);
        }

        if ($this->filled('periodicite') && !$this->filled('periodicite_id')) {
            $id = CodeList::where('type', 'Periodicite')
                ->where('value', strtoupper((string) $this->input('periodicite')))
                ->value('id');

            $this->merge(['periodicite_id' => $id]);
        }
    }

    public function rules(): array
    {
        return [
            'libelle'                => ['required', 'string', 'max:100', $this->libelleUnique()],
            'description'            => ['nullable', 'string', 'max:1000'],
            'periodicite_id'         => ['required', 'integer', $this->periodiciteValide()],
            'prix'                   => ['required', 'numeric', 'min:0', 'max:10000000'],
            'nb_echeances_max'       => ['required', 'integer', 'min:1', 'max:3'],
            'quota_hors_ligne'       => ['nullable', 'integer', 'min:0', 'max:1000'],
            'jours_essai'            => ['nullable', 'integer', 'min:0', 'max:90'],
            'limite_chapitres_essai' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'active'                 => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'libelle.required' => 'Le libellé est obligatoire.',
            'periodicite_id.required' => 'Choisissez une périodicité.',
            'prix.required' => 'Le prix est obligatoire.',
            'prix.min' => 'Le prix ne peut pas être négatif.',
            'nb_echeances_max.min' => 'Au moins 1 échéance.',
            'nb_echeances_max.max' => 'Le paiement peut être fractionné en 3 fois au maximum.',
            'jours_essai.max' => 'L’essai gratuit ne peut pas dépasser 90 jours.',
            'limite_chapitres_essai.min' => 'Au moins 1 chapitre doit être accessible pendant l’essai.',
        ];
    }

    /**
     * Règles qui croisent plusieurs champs (dictionnaire de données) :
     * - paiement fractionné réservé aux formules semestrielle et annuelle ;
     * - limiteChapitresEssai obligatoire dès que joursEssai > 0.
     * Un champ absent (modification partielle) reprend la valeur actuelle de la formule.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $actuelle = $this->formuleActuelle();

            // Paiement fractionné
            $periodiciteId = $this->has('periodicite_id') ? $this->input('periodicite_id') : $actuelle?->periodicite_id;
            $echeances = (int) ($this->has('nb_echeances_max') ? $this->input('nb_echeances_max') : ($actuelle?->nb_echeances_max ?? 1));
            $periodicite = $periodiciteId ? CodeList::whereKey($periodiciteId)->value('value') : null;

            if ($periodicite === 'MENSUEL' && $echeances > 1) {
                $v->errors()->add('nb_echeances_max', 'Une formule mensuelle se paie en une seule fois.');
            }

            // Essai gratuit
            $joursEssai = (int) ($this->has('jours_essai') ? $this->input('jours_essai') : ($actuelle?->jours_essai ?? 0));
            $limite = $this->has('limite_chapitres_essai') ? $this->input('limite_chapitres_essai') : $actuelle?->limite_chapitres_essai;

            if ($joursEssai > 0 && (int) $limite < 1) {
                $v->errors()->add('limite_chapitres_essai', 'Indiquez combien de chapitres sont accessibles pendant l’essai.');
            }
        });
    }

    /** Formule modifiée (null à la création). */
    protected function formuleActuelle(): ?FormuleAbonnement
    {
        return null;
    }

    protected function libelleUnique(?int $ignorerId = null): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec) use ($ignorerId) {
            $existe = FormuleAbonnement::query()
                ->whereRaw('LOWER(TRIM(libelle)) = ?', [mb_strtolower(trim((string) $valeur))])
                ->when($ignorerId, fn ($q) => $q->whereKeyNot($ignorerId))
                ->first();

            if ($existe) {
                $echec("Une formule « {$existe->libelle} » existe déjà.");
            }
        };
    }

    protected function periodiciteValide(): \Closure
    {
        return function (string $attribut, mixed $valeur, \Closure $echec) {
            $ok = CodeList::whereKey($valeur)->where('type', 'Periodicite')->exists();
            if (!$ok) {
                $echec('Périodicité invalide.');
            }
        };
    }
}
