<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\FormuleAbonnement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Formules d'abonnement gérées par l'administrateur. */
class FormuleAbonnementService
{
    /**
     * Champs figés dès qu'une formule a été souscrite :
     * les abonnés ont accepté ces conditions.
     */
    private const CHAMPS_FIGES = ['prix', 'periodicite_id', 'duree_jours'];

    /**
     * Lister les formules : ?recherche=…&periodicite_id=…&active=true|false
     */
    public function lister(array $filtres = [], int $parPage = 10): LengthAwarePaginator
    {
        $parPage = max(1, min($parPage, 50));

        $requete = FormuleAbonnement::with('periodicite')->withCount('abonnements');

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $requete->where(function ($query) use ($recherche) {
                $query->where('libelle', 'ilike', $recherche)
                    ->orWhere('description', 'ilike', $recherche);
            });
        }

        if (!empty($filtres['periodicite_id'])) {
            $requete->where('periodicite_id', $filtres['periodicite_id']);
        }

        // « true », « 1 », « false », « 0 » → vrai booléen.
        if (isset($filtres['active']) && $filtres['active'] !== '') {
            $active = filter_var($filtres['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active !== null) {
                $requete->where('active', $active);
            }
        }

        return $requete
            ->orderByDesc('active')
            ->orderBy('prix')
            ->paginate($parPage)
            ->withQueryString();
    }

    /**
     * Consulter une formule.
     */
    public function consulter(int $id): FormuleAbonnement
    {
        $formule = FormuleAbonnement::with('periodicite')->withCount('abonnements')->find($id);

        if (!$formule) {
            throw new RegleMetierException('Formule introuvable.', 404);
        }

        return $formule;
    }

    /**
     * Créer une formule.
     */
    public function creer(array $donnees): FormuleAbonnement
    {
        $formule = FormuleAbonnement::create($donnees);

        return $this->consulter($formule->id);
    }

    /**
     * Modifier une formule.
     * Déjà souscrite : seuls le libellé, la description et l'état actif peuvent changer.
     */
    public function modifier(FormuleAbonnement $formule, array $donnees): FormuleAbonnement
    {
        return DB::transaction(function () use ($formule, $donnees) {

            $formule = FormuleAbonnement::whereKey($formule->id)->lockForUpdate()->firstOrFail();

            if ($formule->abonnements()->exists()) {
                $changes = collect(self::CHAMPS_FIGES)
                    ->filter(fn ($champ) => array_key_exists($champ, $donnees)
                        && (string) $donnees[$champ] !== (string) $formule->getRawOriginal($champ));

                if ($changes->isNotEmpty()) {
                    throw new RegleMetierException(
                        'Cette formule a déjà des abonnés : son prix et sa périodicité ne peuvent plus changer. '
                        . 'Créez une nouvelle formule et désactivez celle-ci.'
                    );
                }
            }

            $formule->update($donnees);

            return $this->consulter($formule->id);
        });
    }

    /**
     * Supprimer une formule jamais souscrite.
     */
    public function supprimer(FormuleAbonnement $formule): void
    {
        if ($formule->abonnements()->exists()) {
            throw new RegleMetierException(
                'Cette formule ne peut pas être supprimée : elle a déjà des abonnements. Désactivez-la plutôt.'
            );
        }

        if (method_exists($formule, 'periodes') && $formule->periodes()->exists()) {
            throw new RegleMetierException(
                'Cette formule ne peut pas être supprimée : elle possède des périodes d’abonnement. Désactivez-la plutôt.'
            );
        }

        $formule->delete();
    }
}
