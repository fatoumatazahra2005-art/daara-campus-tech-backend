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
 * Règle 5 : si un cours d'un parcours publié repasse en validation, le parcours reste publié
 * et ce cours reste accessible à ses inscrits.
 * Parcours archivé : il sort du catalogue (plus de nouvelle inscription), mais ses inscrits
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
     */
    public function catalogue(?int $apprenantId = null)
    {
        return Parcours::with(['statut', 'projets', 'cours' => fn ($q) => $q->with('statut', 'technologie')])
            ->where(function ($q) use ($apprenantId) {
                $q->whereHas('statut', fn ($s) => $s->where('value', 'PUBLIE'));

                if ($apprenantId !== null) {
                    $q->orWhere(fn ($a) => $a
                        ->whereHas('statut', fn ($s) => $s->where('value', 'ARCHIVE'))
                        ->whereHas('inscriptions', fn ($i) => $i->where('apprenant_id', $apprenantId)));
                }
            })
            ->orderByDesc('id')
            ->get();
    }

    /**
     * S'inscrire à un parcours crée une InscriptionCours pour chaque cours PUBLIE du parcours (ou
     * rattache celle qui existe déjà : un cours commun garde une seule inscription et sa progression).
     * Un cours en revalidation au moment de l'inscription est rattaché à sa validation (rattacherCoursPublie).
     */
    public function sInscrire(Utilisateur $utilisateur, Parcours $parcours): InscriptionParcours
    {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        if (! $parcours->aStatut('PUBLIE')) {
            throw new RegleMetierException('Ce parcours n’est pas disponible.', 404);
        }

        if (InscriptionParcours::where('apprenant_id', $utilisateur->id)->where('parcours_id', $parcours->id)->exists()) {
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

            $parcours->cours()->whereHas('statut', fn ($q) => $q->where('value', 'PUBLIE'))->get()
                ->each(function (Cours $cours) use ($utilisateur, &$ids) {
                    $ids[] = $this->inscriptionCours($utilisateur->id, $cours->id)->id;
                });

            $inscription->inscriptionsCours()->syncWithoutDetaching($ids);

            return $this->recalculer($inscription)->load('statut', 'parcours');
        });
    }

    /** Abandonner un parcours n'abandonne pas les inscriptions aux cours. */
    public function abandonner(InscriptionParcours $inscription): InscriptionParcours
    {
        if (! $inscription->aStatut('EN_COURS')) {
            throw new RegleMetierException('Seule une inscription en cours peut être abandonnée.', 422);
        }

        $inscription->update(['statut_id' => CodeList::idOf('StatutInscription', 'ABANDONNEE')]);

        return $inscription->fresh(['statut', 'parcours']);
    }

    /** Reprendre un parcours abandonné : la progression est conservée et recalculée. */
    public function reprendre(InscriptionParcours $inscription): InscriptionParcours
    {
        if (! $inscription->aStatut('ABANDONNEE')) {
            throw new RegleMetierException('Seule une inscription abandonnée peut être reprise.', 422);
        }

        $inscription->update(['statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS')]);

        return $this->recalculer($inscription->fresh(['statut', 'parcours']));
    }

    /**
     * Un cours devient PUBLIE (revalidé, ou ajouté publié à un parcours) :
     * chaque apprenant inscrit à un parcours EN_COURS qui contient ce cours reçoit son InscriptionCours
     * (ou rattache celle qui existe déjà). Sans cela, il ne pourrait jamais terminer un parcours
     * dont ce cours est obligatoire.
     *
     * @return int nombre d'inscriptions de parcours mises à jour
     */
    public function rattacherCoursPublie(Cours $cours): int
    {
        $cours->loadMissing('statut');

        if ($cours->statut?->value !== 'PUBLIE') {
            return 0;
        }

        // Parcours publiés, et archivés : leurs inscrits continuent de les suivre.
        $parcoursIds = $cours->parcours()
            ->whereHas('statut', fn ($q) => $q->whereIn('value', ['PUBLIE', 'ARCHIVE']))
            ->pluck((new Parcours)->getTable().'.id');

        if ($parcoursIds->isEmpty()) {
            return 0;
        }

        $nb = 0;

        InscriptionParcours::whereIn('parcours_id', $parcoursIds)
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->chunkById(100, function ($lot) use ($cours, &$nb) {
                foreach ($lot as $inscriptionParcours) {
                    DB::transaction(function () use ($inscriptionParcours, $cours, &$nb) {
                        $inscriptionCours = $this->inscriptionCours($inscriptionParcours->apprenant_id, $cours->id);

                        $ajoutes = $inscriptionParcours->inscriptionsCours()->syncWithoutDetaching([$inscriptionCours->id]);

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
     * Progression = moyenne des cours obligatoires. TERMINEE quand tous les cours obligatoires sont
     * TERMINEE et que les rendus de tous les projets du parcours sont CORRIGE.
     */
    public function recalculer(InscriptionParcours $inscription): InscriptionParcours
    {
        $inscription->loadMissing('statut', 'parcours');

        if (! $inscription->aStatut('EN_COURS')) {
            return $inscription;
        }

        $tc = (new Cours)->getTable();
        $obligatoires = $inscription->parcours->cours()->wherePivot('obligatoire', true)->pluck("$tc.id");

        if ($obligatoires->isEmpty()) {
            return $inscription;
        }

        $inscriptions = InscriptionCours::with('statut')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereIn('cours_id', $obligatoires)
            ->get();

        // Un cours obligatoire sans inscription (ex. en revalidation lors de l'inscription au parcours) compte 0 %.
        $inscription->update(['progression' => round($inscriptions->sum('progression') / $obligatoires->count(), 2)]);

        $tousTermines = $inscriptions->count() === $obligatoires->count()
            && $inscriptions->every(fn (InscriptionCours $i) => $i->aStatut('TERMINEE'));

        if (! $tousTermines) {
            return $inscription->fresh(['statut', 'parcours']);
        }

        $this->attribuerProjets($inscription);

        $projets = Projet::where('parcours_id', $inscription->parcours_id)->pluck('id');
        $rendus = RenduProjet::with('statut')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereIn('projet_id', $projets)
            ->get();

        $projetsCorriges = $rendus->count() === $projets->count()
            && $rendus->every(fn (RenduProjet $r) => $r->aStatut('CORRIGE'));

        if ($projets->isEmpty() || $projetsCorriges) {
            $inscription->update([
                'statut_id' => CodeList::idOf('StatutInscription', 'TERMINEE'),
                'progression' => 100,
            ]);
        }

        return $inscription->fresh(['statut', 'parcours']);
    }

    /** À appeler après chaque changement de progression ou de statut d'une inscription de cours. */
    public function recalculerPourInscriptionCours(InscriptionCours $inscriptionCours): void
    {
        InscriptionParcours::where('apprenant_id', $inscriptionCours->apprenant_id)
            ->whereHas('inscriptionsCours', fn ($q) => $q->where($inscriptionCours->getTable().'.id', $inscriptionCours->id))
            ->get()
            ->each(fn (InscriptionParcours $i) => $this->recalculer($i));
    }

    /**
     * Recalcule toutes les inscriptions EN_COURS d'un parcours.
     * À appeler quand l'admin change la composition du parcours (cours ajouté ou retiré,
     * obligatoire ↔ facultatif) ou ajoute un projet : les rendus sont attribués à ceux qui ont fini les cours.
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

    /** Un rendu (A_FAIRE) est créé par projet quand tous les cours obligatoires sont terminés. */
    private function attribuerProjets(InscriptionParcours $inscription): void
    {
        Projet::where('parcours_id', $inscription->parcours_id)->get()->each(function (Projet $projet) use ($inscription) {
            RenduProjet::firstOrCreate(
                ['projet_id' => $projet->id, 'apprenant_id' => $inscription->apprenant_id],
                [
                    'date_attribution' => today(),
                    'date_limite' => today()->addDays((int) $projet->delai_jours),
                    'statut_id' => CodeList::idOf('StatutRendu', 'A_FAIRE'),
                ]
            );
        });
    }

    /** Inscription au cours de l'apprenant (créée si besoin) : un cours commun garde une seule inscription. */
    private function inscriptionCours(int $apprenantId, int $coursId): InscriptionCours
    {
        return InscriptionCours::firstOrCreate(
            ['apprenant_id' => $apprenantId, 'cours_id' => $coursId],
            [
                'date_inscription' => today(),
                'progression' => 0,
                'statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS'),
            ]
        );
    }
}
