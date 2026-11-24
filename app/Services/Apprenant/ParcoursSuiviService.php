<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\InscriptionParcours;
use App\Models\Parcours;
use App\Models\Projet;
use App\Models\RenduProjet;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Inscription aux parcours, progression des parcours et attribution des projets.
 *
 * Règle 5 :
 * Si un cours d'un parcours publié repasse en validation, le parcours reste publié
 * et ce cours reste accessible à ses inscrits.
 *
 * Règle concernant les projets :
 * - Les projets actifs au moment de l'inscription sont attribués à l'apprenant.
 * - Un projet attribué reste accessible même s'il est désactivé ensuite.
 * - Un projet ajouté après l'inscription n'est pas attribué aux anciens inscrits.
 * - La désactivation ou la réactivation d'un projet ne modifie pas les obligations
 *   des apprenants déjà inscrits.
 *
 * Parcours archivé :
 * Il sort du catalogue (plus de nouvelle inscription), mais ses inscrits
 * le continuent jusqu'au bout (cours, progression, projets).
 */
class ParcoursSuiviService
{
    public function __construct(private AccesService $acces)
    {
    }

    /**
     * Catalogue des parcours (visible avec ou sans abonnement) :
     * - tous les parcours PUBLIE ;
     * - pour un apprenant donné : aussi les parcours ARCHIVE auxquels il est inscrit,
     *   pour qu'il puisse ouvrir leur fiche et les terminer.
     * Seuls les projets actifs sont présentés.
     */



    public function catalogue(?int $apprenantId = null)
    {
        return Parcours::with([
            'statut',

            'cours' => fn ($q) => $q->with('statut', 'technologie'),

            'inscriptions' => function ($q) use ($apprenantId) {
                if ($apprenantId !== null) {
                    $q->where('apprenant_id', $apprenantId);
                }
            },

            'projets' => function ($q) use ($apprenantId) {
                if ($apprenantId !== null) {
                    $q->where(function ($projets) use ($apprenantId) {
                        $projets->where('actif', true)
                            ->orWhereHas('rendus', function ($rendus) use ($apprenantId) {
                                $rendus->where('apprenant_id', $apprenantId);
                            });
                    })->with([
                        'rendus' => fn ($rendus) =>
                        $rendus->where('apprenant_id', $apprenantId),
                    ]);
                } else {
                    $q->where('actif', true);
                }
            },
        ])
            ->where(function ($q) use ($apprenantId) {
                $q->whereHas('statut', fn ($s) =>
                $s->where('value', 'PUBLIE')
                );

                if ($apprenantId !== null) {
                    $q->orWhere(fn ($a) => $a
                        ->whereHas('statut', fn ($s) =>
                        $s->where('value', 'ARCHIVE')
                        )
                        ->whereHas('inscriptions', fn ($i) =>
                        $i->where('apprenant_id', $apprenantId)
                        )
                    );
                }
            })
            ->orderByDesc('id')
            ->get();
    }


    /**
     * Inscrire un apprenant à un parcours.
     *
     * - Crée l'inscription au parcours.
     * - Crée ou récupère les inscriptions aux cours publiés.
     * - Attribue les projets actifs au moment de l'inscription.
     * - Les rendus sont créés avec le statut EN_ATTENTE.
     */
    public function sInscrire(Utilisateur $utilisateur, Parcours $parcours): InscriptionParcours
    {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        if (! $parcours->aStatut('PUBLIE')) {
            throw new RegleMetierException('Ce parcours n’est pas disponible.', 404);
        }

        if (
            InscriptionParcours::where('apprenant_id', $utilisateur->id)
                ->where('parcours_id', $parcours->id)
                ->exists()
        ) {
            throw new RegleMetierException('Vous êtes déjà inscrit à ce parcours.', 409);
        }

        return DB::transaction(function () use ($utilisateur, $parcours) {
            $inscription = InscriptionParcours::create([
                'date_inscription' => today(),
                'progression' => 0,
                'statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS'),
                'apprenant_id' => $utilisateur->id,
                'parcours_id' => $parcours->id,
            ]);

            $ids = [];

            $parcours->cours()
                ->whereHas('statut', fn ($q) => $q->where('value', 'PUBLIE'))
                ->get()
                ->each(function (Cours $cours) use ($utilisateur, &$ids) {
                    $ids[] = $this->inscriptionCours($utilisateur->id, $cours->id)->id;
                });

            $inscription->inscriptionsCours()->syncWithoutDetaching($ids);

            // Les projets actifs à la date d'inscription sont attribués.
            // Leur délai ne commence pas encore.
            $this->attribuerProjetsEnAttente($inscription);

            return $this->recalculer($inscription)->load('statut', 'parcours');
        });
    }

    /**
     * Abandonner un parcours n'abandonne pas les inscriptions aux cours.
     */
    public function abandonner(InscriptionParcours $inscription): InscriptionParcours
    {
        if (! $inscription->aStatut('EN_COURS')) {
            throw new RegleMetierException('Seule une inscription en cours peut être abandonnée.', 422);
        }

        $inscription->update([
            'statut_id' => CodeList::idOf('StatutInscription', 'ABANDONNEE'),
        ]);

        return $inscription->fresh(['statut', 'parcours']);
    }

    /**
     * Reprendre un parcours abandonné :
     * la progression est conservée et recalculée.
     */
    public function reprendre(InscriptionParcours $inscription): InscriptionParcours
    {
        if (! $inscription->aStatut('ABANDONNEE')) {
            throw new RegleMetierException('Seule une inscription abandonnée peut être reprise.', 422);
        }

        $inscription->update([
            'statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS'),
        ]);

        return $this->recalculer($inscription->fresh(['statut', 'parcours']));
    }

    /**
     * Un cours devient PUBLIE (revalidé, ou ajouté publié à un parcours) :
     * chaque apprenant inscrit à un parcours EN_COURS qui contient ce cours reçoit
     * son InscriptionCours (ou rattache celle qui existe déjà).
     *
     * @return int Nombre d'inscriptions de parcours mises à jour.
     */
    public function rattacherCoursPublie(Cours $cours): int
    {
        $cours->loadMissing('statut');

        if ($cours->statut?->value !== 'PUBLIE') {
            return 0;
        }

        // Parcours publiés et archivés : leurs inscrits continuent de les suivre.
        $parcoursIds = $cours->parcours()
            ->whereHas('statut', fn ($q) => $q->whereIn('value', ['PUBLIE', 'ARCHIVE']))
            ->pluck((new Parcours)->getTable() . '.id');

        if ($parcoursIds->isEmpty()) {
            return 0;
        }

        $nb = 0;

        InscriptionParcours::whereIn('parcours_id', $parcoursIds)
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->chunkById(100, function ($lot) use ($cours, &$nb) {
                foreach ($lot as $inscriptionParcours) {
                    DB::transaction(function () use ($inscriptionParcours, $cours, &$nb) {
                        $inscriptionCours = $this->inscriptionCours(
                            $inscriptionParcours->apprenant_id,
                            $cours->id
                        );

                        $ajoutes = $inscriptionParcours->inscriptionsCours()
                            ->syncWithoutDetaching([$inscriptionCours->id]);

                        if (! empty($ajoutes['attached'])) {
                            $this->recalculer($inscriptionParcours);
                            $nb++;
                        }
                    });
                }
            });

        return $nb;
    }

    /**
     * Progression = moyenne des cours obligatoires.
     *
     * Le parcours est TERMINEE lorsque :
     * - tous les cours obligatoires sont terminés ;
     * - tous les projets attribués à l'apprenant sont corrigés.
     *
     * Un projet désactivé après l'inscription reste pris en compte
     * s'il avait déjà été attribué à l'apprenant.
     */
    public function recalculer(InscriptionParcours $inscription): InscriptionParcours
    {
        $inscription->loadMissing('statut', 'parcours');

        // Ne pas recalculer les parcours abandonnés ou terminés.
        if (! $inscription->aStatut('EN_COURS')) {
            return $inscription;
        }

        $tc = (new Cours)->getTable();

        $obligatoires = $inscription->parcours
            ->cours()
            ->wherePivot('obligatoire', true)
            ->pluck("$tc.id");

        if ($obligatoires->isEmpty()) {
            return $inscription;
        }

        $inscriptions = InscriptionCours::with('statut')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereIn('cours_id', $obligatoires)
            ->get();

        // Un cours obligatoire sans inscription compte 0 %.
        $inscription->update([
            'progression' => round(
                $inscriptions->sum('progression') / $obligatoires->count(),
                2
            ),
        ]);

        $tousTermines = $inscriptions->count() === $obligatoires->count()
            && $inscriptions->every(
                fn (InscriptionCours $i) => $i->aStatut('TERMINEE')
            );

        // Tant que les cours obligatoires ne sont pas terminés,
        // les délais des projets restent en attente.
        if (! $tousTermines) {
            return $inscription->fresh(['statut', 'parcours']);
        }

        // Démarrer les délais des projets déjà attribués à l'inscription.
        $this->demarrerProjetsEnAttente($inscription);

        // Récupérer tous les rendus attribués pour ce parcours.
        // Ne surtout pas filtrer sur projets.actif :
        // un projet désactivé reste une obligation pour son ancien inscrit.
        $rendus = RenduProjet::with('statut')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereHas('projet', function ($q) use ($inscription) {
                $q->where('parcours_id', $inscription->parcours_id);
            })
            ->get();

        // Tous les projets attribués doivent être corrigés.
        // Si aucun projet n'a été attribué, la condition est satisfaite.
        $projetsCorriges = $rendus->every(
            fn (RenduProjet $r) => $r->aStatut('CORRIGE')
        );

        if ($projetsCorriges) {
            $inscription->update([
                'statut_id' => CodeList::idOf('StatutInscription', 'TERMINEE'),
                'progression' => 100,
            ]);
        }

        return $inscription->fresh(['statut', 'parcours']);
    }

    /**
     * À appeler après chaque changement de progression
     * ou de statut d'une inscription de cours.
     */
    public function recalculerPourInscriptionCours(InscriptionCours $inscriptionCours): void
    {
        InscriptionParcours::where('apprenant_id', $inscriptionCours->apprenant_id)
            ->whereHas('inscriptionsCours', fn ($q) => $q->where(
                $inscriptionCours->getTable() . '.id',
                $inscriptionCours->id
            ))
            ->get()
            ->each(fn (InscriptionParcours $i) => $this->recalculer($i));
    }

    /**
     * Recalcule toutes les inscriptions EN_COURS d'un parcours.
     *
     * À appeler lorsque la composition des cours change
     * (cours ajouté ou retiré, obligatoire ↔ facultatif).
     *
     * Ne pas appeler après l'ajout, l'activation ou la désactivation
     * d'un projet : ces opérations ne doivent pas modifier les obligations
     * des apprenants déjà inscrits.
     */
    public function recalculerParcours(int $parcoursId): void
    {
        InscriptionParcours::where('parcours_id', $parcoursId)
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->chunkById(100, function ($lot) {
                foreach ($lot as $inscription) {
                    $this->recalculer($inscription);
                }
            });
    }

    /**
     * Attribue les projets actifs au moment de l'inscription.
     *
     * Les rendus sont créés avec le statut EN_ATTENTE.
     * La date limite est provisoire et le délai ne commence pas encore.
     */
    private function attribuerProjetsEnAttente(InscriptionParcours $inscription): void
    {
        Projet::where('parcours_id', $inscription->parcours_id)
            ->where('actif', true)
            ->get()
            ->each(function (Projet $projet) use ($inscription) {
                RenduProjet::firstOrCreate(
                    [
                        'projet_id' => $projet->id,
                        'apprenant_id' => $inscription->apprenant_id,
                    ],
                    [
                        'date_attribution' => today(),
                        'date_limite' => today(),
                        'statut_id' => CodeList::idOf('StatutRendu', 'EN_ATTENTE'),
                    ]
                );
            });
    }

    /**
     * Démarre le délai des projets déjà attribués
     * lorsque tous les cours obligatoires sont terminés.
     *
     * On ne filtre pas les projets actifs :
     * les projets désactivés après l'inscription restent concernés.
     */
    private function demarrerProjetsEnAttente(InscriptionParcours $inscription): void
    {
        RenduProjet::with('projet')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereHas('projet', function ($q) use ($inscription) {
                $q->where('parcours_id', $inscription->parcours_id);
            })
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_ATTENTE'))
            ->get()
            ->each(function (RenduProjet $rendu) {
                $rendu->update([
                    'date_attribution' => today(),
                    'date_limite' => today()->addDays(
                        (int) $rendu->projet->delai_jours
                    ),
                    'statut_id' => CodeList::idOf('StatutRendu', 'A_FAIRE'),
                ]);
            });
    }

    /**
     * Inscription au cours de l'apprenant (créée si besoin) :
     * un cours commun garde une seule inscription.
     */
    private function inscriptionCours(int $apprenantId, int $coursId): InscriptionCours
    {
        return InscriptionCours::firstOrCreate(
            [
                'apprenant_id' => $apprenantId,
                'cours_id' => $coursId,
            ],
            [
                'date_inscription' => today(),
                'progression' => 0,
                'statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS'),
            ]
        );
    }
}
