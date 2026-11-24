<?php

namespace App\Services\Encadreur;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\InscriptionParcours;
use App\Models\RenduProjet;
use App\Models\Utilisateur;
use App\Services\Apprenant\ParcoursSuiviService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Correction des rendus de projet par l'encadreur affecté (RG35).
 *
 * Règles :
 * - Seul l'encadreur affecté au rendu peut le corriger.
 * - Seul un rendu soumis (RENDU) peut être corrigé.
 * - Si la note atteint la note minimale, le rendu passe à CORRIGE.
 * - Sinon, il passe à A_REPRENDRE avec un nouveau délai.
 * - Un projet désactivé peut toujours être corrigé pour les apprenants
 *   auxquels il avait déjà été attribué.
 * - La validation d'un rendu déclenche le recalcul du parcours.
 * - Une inscription TERMINEE reste TERMINEE.
 */
class CorrectionService
{
    public const VUES = [
        'a_corriger',
        'corriges',
        'a_reprendre',
        'tous',
    ];

    private const RELATIONS = [
        'statut',
        'projet.parcours',
        'apprenant.utilisateur',
    ];

    public function __construct(
        private ParcoursSuiviService $suivi
    ) {
    }

    /**
     * Lister les rendus affectés à l'encadreur.
     */
    public function lister(
        Utilisateur $encadreur,
        array $filtres = []
    ): LengthAwarePaginator {

        $vue = $filtres['vue'] ?? 'a_corriger';

        if (!in_array($vue, self::VUES, true)) {
            throw new RegleMetierException('Vue de correction invalide.');
        }

        $q = RenduProjet::with(self::RELATIONS)
            ->where('correcteur_id', $encadreur->id);

        match ($vue) {
            'a_corriger' => $q->whereHas(
                'statut',
                fn ($s) => $s->where('value', 'RENDU')
            ),

            'corriges' => $q->whereHas(
                'statut',
                fn ($s) => $s->where('value', 'CORRIGE')
            ),

            'a_reprendre' => $q->whereHas(
                'statut',
                fn ($s) => $s->whereIn(
                    'value',
                    ['A_REPRENDRE', 'EN_RETARD']
                )
            ),

            'tous' => null,
        };

        $parPage = max(
            1,
            min((int) ($filtres['par_page'] ?? 15), 50)
        );

        return $q->orderByRaw('date_rendu IS NULL')
            ->orderBy('date_rendu')
            ->paginate($parPage)
            ->withQueryString();
    }

    /**
     * Compteurs des rendus de l'encadreur.
     */
    public function compteurs(Utilisateur $encadreur): array
    {
        $base = fn () => RenduProjet::where(
            'correcteur_id',
            $encadreur->id
        );

        return [
            'a_corriger' => $base()
                ->whereHas(
                    'statut',
                    fn ($s) => $s->where('value', 'RENDU')
                )
                ->count(),

            'a_reprendre' => $base()
                ->whereHas(
                    'statut',
                    fn ($s) => $s->whereIn(
                        'value',
                        ['A_REPRENDRE', 'EN_RETARD']
                    )
                )
                ->count(),

            'corriges' => $base()
                ->whereHas(
                    'statut',
                    fn ($s) => $s->where('value', 'CORRIGE')
                )
                ->count(),

            'tous' => $base()->count(),
        ];
    }

    /**
     * Consulter un rendu affecté à l'encadreur.
     */
    public function details(
        Utilisateur $encadreur,
        int $id
    ): RenduProjet {

        $rendu = RenduProjet::with(self::RELATIONS)
            ->where('correcteur_id', $encadreur->id)
            ->find($id);

        // Introuvable aussi si le rendu est affecté à un autre encadreur.
        if (!$rendu) {
            throw new RegleMetierException(
                'Rendu introuvable.',
                404
            );
        }

        return $rendu;
    }

    /**
     * Corriger un rendu.
     *
     * CORRIGE si note >= note minimale du projet.
     * A_REPRENDRE sinon, avec une nouvelle date limite.
     *
     * Le projet peut être actif ou désactivé :
     * son état ne bloque jamais la correction d'un rendu déjà attribué.
     */
    public function corriger(
        Utilisateur $encadreur,
        int $id,
        float $note,
        string $commentaire
    ): RenduProjet {

        return DB::transaction(function () use (
            $encadreur,
            $id,
            $note,
            $commentaire
        ) {

            $rendu = RenduProjet::with(['statut', 'projet'])
                ->where('correcteur_id', $encadreur->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$rendu) {
                throw new RegleMetierException(
                    'Rendu introuvable.',
                    404
                );
            }

            // Seul un rendu soumis peut être corrigé.
            if ($rendu->statut?->value !== 'RENDU') {
                throw new RegleMetierException(
                    'Seul un rendu soumis par l’apprenant peut être corrigé.'
                );
            }

            // La désactivation du projet ne bloque pas la correction.
            $noteMinimale = (float) (
                $rendu->projet?->note_minimale ?? 10
            );

            $valide = $note >= $noteMinimale;

            $donnees = [
                'note' => $note,
                'commentaire' => $commentaire,
                'statut_id' => CodeList::idOf(
                    'StatutRendu',
                    $valide ? 'CORRIGE' : 'A_REPRENDRE'
                ),
            ];

            if (!$valide) {
                // Nouveau délai pour permettre à l'apprenant
                // de reprendre et soumettre son projet.
                $delai = max(
                    1,
                    (int) ($rendu->projet?->delai_jours ?? 7)
                );

                $donnees['date_limite'] = today()->addDays($delai);
            }

            $rendu->update($donnees);

            /*
             * Si le rendu est validé, recalculer le parcours.
             *
             * Le ParcoursSuiviService :
             * - vérifie tous les rendus attribués au parcours ;
             * - tient compte des projets désactivés ;
             * - ne modifie pas une inscription déjà TERMINEE.
             *
             * Si le rendu est refusé, le parcours ne peut pas
             * être terminé grâce à ce rendu : pas de recalcul nécessaire.
             */
            if ($valide && $rendu->projet) {

                InscriptionParcours::where(
                    'apprenant_id',
                    $rendu->apprenant_id
                )
                    ->where(
                        'parcours_id',
                        $rendu->projet->parcours_id
                    )
                    ->get()
                    ->each(
                        fn ($inscription) =>
                        $this->suivi->recalculer($inscription)
                    );
            }

            return $rendu->fresh(self::RELATIONS);
        });
    }
}
