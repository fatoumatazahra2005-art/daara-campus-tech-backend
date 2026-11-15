<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Abonnement;
use App\Models\Chapitre;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\Module;
use App\Models\PeriodeAbonnement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Règles d'accès au contenu : abonnement, période en cours, essai limité, cours lisibles.
 *
 * Sans abonnement qui donne accès (règle 7) : le catalogue reste visible, mais aucun chapitre ne s'ouvre ;
 * progression, XP et rendus sont conservés ; les certificats restent consultables.
 */
class AccesService
{
    public const STATUTS_OUVERTS = ['EN_ATTENTE_PAIEMENT', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE'];

    public const STATUTS_ACCES = ['ESSAI', 'ACTIF', 'RESILIE'];

    /** L'abonnement « en cours » d'un apprenant (un seul à la fois). */
    public function abonnementOuvert(int $apprenantId): ?Abonnement
    {
        return Abonnement::with('statut')
            ->where('apprenant_id', $apprenantId)
            ->whereHas('statut', fn ($q) => $q->whereIn('value', self::STATUTS_OUVERTS))
            ->latest('id')
            ->first();
    }

    /** Période dont dateDebut <= aujourd'hui <= dateFin. */
    public function periodeEnCours(Abonnement $abonnement): ?PeriodeAbonnement
    {
        return $abonnement->periodes()
            ->whereDate('date_debut', '<=', today())
            ->whereDate('date_fin', '>=', today())
            ->first();
    }

    /** Abonnement.donneAcces() : ESSAI, ACTIF ou RESILIE, et la date du jour dans la période en cours. */
    public function donneAcces(Abonnement $abonnement): bool
    {
        $abonnement->loadMissing('statut');

        return in_array($abonnement->statut?->value, self::STATUTS_ACCES, true)
            && $this->periodeEnCours($abonnement) !== null;
    }

    /** L'apprenant a-t-il, aujourd'hui, un abonnement qui donne accès au contenu ? */
    public function aAcces(int $apprenantId): bool
    {
        $abonnement = $this->abonnementOuvert($apprenantId);

        return $abonnement !== null && $this->donneAcces($abonnement);
    }

    public function exigerAbonnementActif(int $apprenantId): Abonnement
    {
        $abonnement = $this->abonnementOuvert($apprenantId);

        if (! $abonnement || ! $this->donneAcces($abonnement)) {
            throw new RegleMetierException('Un abonnement actif est requis pour accéder au contenu.', 403);
        }

        return $abonnement;
    }

    /** Nombre de chapitres accessibles par cours pendant un essai non payé, sinon null (règle 6). */
    public function limiteEssai(Abonnement $abonnement): ?int
    {
        $periode = $this->periodeEnCours($abonnement);

        if (! $periode || ! $periode->est_essai) {
            return null;
        }

        // Payé pendant l'essai : la première échéance de la période payante est VALIDE,
        // l'abonnement est ACTIF et tout est débloqué ; les jours d'essai restants sont offerts.
        $periodePayee = $abonnement->periodes()
            ->where('est_essai', false)
            ->whereHas('paiements', fn ($q) => $q->where('numero_echeance', 1)
                ->whereHas('statut', fn ($s) => $s->where('value', 'VALIDE')))
            ->exists();

        if ($periodePayee) {
            return null;
        }

        $periode->loadMissing('formule');

        return (int) ($periode->formule->limite_chapitres_essai ?? 0);
    }

    /** Ids des chapitres actifs d'un cours (modules actifs), dans l'ordre module puis chapitre. */
    public function chapitreIdsActifs(int $coursId): Collection
    {
        return $this->requeteChapitresActifs($coursId)->pluck((new Chapitre)->getTable().'.id');
    }

    public function requeteChapitresActifs(int $coursId): Builder
    {
        $tc = (new Chapitre)->getTable();
        $tm = (new Module)->getTable();

        return Chapitre::query()
            ->join($tm, "$tm.id", '=', "$tc.module_id")
            ->where("$tm.cours_id", $coursId)
            ->where("$tm.actif", true)
            ->where("$tc.actif", true)
            ->orderBy("$tm.ordre")
            ->orderBy("$tc.ordre")
            ->select("$tc.*");
    }

    /** En essai, seuls les `limiteChapitresEssai` premiers chapitres actifs de chaque cours sont ouverts (règle 6). */
    public function exigerChapitreAccessible(Abonnement $abonnement, Chapitre $chapitre, Cours $cours): void
    {
        $limite = $this->limiteEssai($abonnement);

        if ($limite === null) {
            return;
        }

        $rang = $this->chapitreIdsActifs($cours->id)->values()->search($chapitre->id);

        if ($rang === false || $rang >= $limite) {
            throw new RegleMetierException('Ce chapitre n’est pas inclus dans votre essai gratuit.', 403);
        }
    }

    /**
     * Un cours est-il lisible par un apprenant déjà inscrit, selon son statut ?
     * - PUBLIE ;
     * - EN_ATTENTE_VALIDATION d'un cours déjà publié (revalidation, règle 1) ;
     * - REFUSE d'un cours déjà publié (modification refusée : les inscrits gardent le cours) ;
     * - ARCHIVE (règle 2).
     */
    public function lisiblePourInscrit(Cours $cours): bool
    {
        $cours->loadMissing('statut');
        $statut = $cours->statut?->value;
        $dejaPublie = $cours->date_publication !== null;

        return $statut === 'PUBLIE'
            || $statut === 'ARCHIVE'
            || (in_array($statut, ['EN_ATTENTE_VALIDATION', 'REFUSE'], true) && $dejaPublie);
    }

    /** Un cours est lisible s'il est publié, ou lisible pour un inscrit (revalidation, refus après publication, archive). */
    public function exigerCoursAccessible(int $apprenantId, Cours $cours): void
    {
        $cours->loadMissing('statut');

        if ($cours->statut?->value === 'PUBLIE') {
            return;
        }

        $inscrit = InscriptionCours::where('apprenant_id', $apprenantId)->where('cours_id', $cours->id)->exists();

        if ($inscrit && $this->lisiblePourInscrit($cours)) {
            return;
        }

        throw new RegleMetierException('Cours introuvable.', 404);
    }
}
