<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Abonnement;
use App\Models\Apprenant;
use App\Models\Chapitre;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\Module;
use App\Models\ProgressionChapitre;
use App\Models\Quiz;
use App\Models\TentativeQuiz;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecture des chapitres, progression, XP / niveau / série, achèvement d'un cours.
 *
 * Règle 7 : sans abonnement qui donne accès, aucun chapitre ne s'ouvre, même déjà lu.
 * Règle 4 : une inscription TERMINEE est figée (100 %, certificat acquis) ; une inscription EN_COURS
 *           est recalculée sur les chapitres actifs (y compris après une activation / désactivation).
 */
class ProgressionService
{
    /** Un palier de niveau tous les 500 XP. */
    public const PALIER_XP = 500;

    public function __construct(
        private AccesService $acces,
        private CertificatService $certificats,
        private ParcoursSuiviService $parcours,
    ) {
    }

    /**
     * Vérifie tout ce qui conditionne la lecture d'un chapitre (cours lisible, abonnement, inscription,
     * limite d'essai) et retourne le contexte. Utilisé aussi par les quiz et les téléchargements.
     *
     * @return array{apprenant: Apprenant, cours: Cours, abonnement: Abonnement, inscription: InscriptionCours}
     */
    public function contexte(Utilisateur $utilisateur, Chapitre $chapitre): array
    {
        $chapitre->loadMissing('module.cours.statut');

        if (! $chapitre->actif || ! $chapitre->module?->actif) {
            throw new RegleMetierException('Chapitre introuvable.', 404);
        }

        $cours = $chapitre->module->cours;

        $this->acces->exigerCoursAccessible($utilisateur->id, $cours);
        $abonnement = $this->acces->exigerAbonnementActif($utilisateur->id);

        $inscription = InscriptionCours::with('statut')
            ->where('apprenant_id', $utilisateur->id)->where('cours_id', $cours->id)->first();

        if (! $inscription) {
            throw new RegleMetierException('Inscrivez-vous d’abord à ce cours.', 422);
        }

        if ($inscription->aStatut('ABANDONNEE')) {
            throw new RegleMetierException('Reprenez ce cours pour continuer à le suivre.', 422);
        }

        $this->acces->exigerChapitreAccessible($abonnement, $chapitre, $cours);

        return [
            'apprenant' => Apprenant::findOrFail($utilisateur->id),
            'cours' => $cours,
            'abonnement' => $abonnement,
            'inscription' => $inscription,
        ];
    }

    /**
     * Plan du cours : modules et chapitres actifs, étapes des projets guidés,
     * chapitres terminés, verrous (pas d'abonnement, ou au-delà de l'essai) et quiz actifs.
     */
    public function structure(Utilisateur $utilisateur, Cours $cours): array
    {
        // 1. Vérifier que le cours est accessible (publié, ou lisible pour un inscrit).
        $this->acces->exigerCoursAccessible($utilisateur->id, $cours);

        // 2. Abonnement qui donne accès aujourd'hui ?
        $abonnement = $this->acces->abonnementOuvert($utilisateur->id);
        $acces = $abonnement && $this->acces->donneAcces($abonnement);

        // 3. Limite de l'essai éventuelle.
        $limite = $acces ? $this->acces->limiteEssai($abonnement) : null;

        // 4. Inscription de l'apprenant.
        $inscription = InscriptionCours::with('statut')
            ->where('apprenant_id', $utilisateur->id)
            ->where('cours_id', $cours->id)
            ->first();

        // 5. Chapitres terminés et déjà ouverts.
        $termines = $inscription
            ? ProgressionChapitre::where('inscription_cours_id', $inscription->id)
                ->where('est_termine', true)
                ->pluck('chapitre_id')
                ->all()
            : [];

        $ouverts = $inscription
            ? ProgressionChapitre::where('inscription_cours_id', $inscription->id)
                ->pluck('chapitre_id')
                ->all()
            : [];

        // 6. Rang du chapitre dans le cours (limite de l'essai).
        $rang = 0;

        // 7. Modules, chapitres, quiz et étapes des projets (actifs seulement).
        $modules = Module::where('cours_id', $cours->id)
            ->where('actif', true)
            ->orderBy('ordre')
            ->with([
                'chapitres' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
                'chapitres.quiz' => fn ($q) => $q->where('actif', true),
                'chapitres.quiz.mode',
                'chapitres.etapesProjetGuide' => function ($q) use ($inscription) {
                    $q->orderBy('ordre')->with([
                        'projetGuide:id,titre',
                        'progressions' => fn ($p) => $inscription
                            ? $p->where('inscription_cours_id', $inscription->id)
                            : $p->whereRaw('1 = 0'),
                    ]);
                },
            ])
            ->get()
            ->map(function (Module $module) use (&$rang, $acces, $limite, $termines, $ouverts) {
                return [
                    'id' => $module->id,
                    'titre' => $module->titre,
                    'description' => $module->description,
                    'ordre' => $module->ordre,

                    'chapitres' => $module->chapitres
                        ->map(function (Chapitre $c) use (&$rang, $acces, $limite, $termines, $ouverts) {
                            // Verrou (règle 7) : sans abonnement qui donne accès, tout chapitre est verrouillé,
                            // même déjà lu ; pendant l'essai, au-delà de la limite de chapitres (règle 6).
                            $verrouille = ! $acces || ($limite !== null && $rang >= $limite);

                            $rang++;

                            $etapes = $c->etapesProjetGuide
                                ->map(fn ($e) => [
                                    'id' => $e->id,
                                    'ordre' => $e->ordre,
                                    'consigne' => $e->consigne,
                                    'code_depart' => $e->code_depart,
                                    'tests_attendus' => $e->tests_attendus,
                                    'projet_guide' => [
                                        'id' => $e->projetGuide?->id,
                                        'titre' => $e->projetGuide?->titre,
                                    ],
                                    'terminee' => $e->progressions->contains(fn ($p) => $p->est_terminee),
                                ])
                                ->values();

                            return [
                                'id' => $c->id,
                                'titre' => $c->titre,
                                'objectif' => $c->objectif,
                                'ordre' => $c->ordre,
                                'duree_estimee' => $c->duree_estimee,
                                'xp_recompense' => $c->xp_recompense,
                                'termine' => in_array($c->id, $termines, true),
                                'deja_ouvert' => in_array($c->id, $ouverts, true),
                                'verrouille' => $verrouille,
                                'etapes_projet_guide' => $etapes->all(),
                                'nombre_etapes' => $etapes->count(),
                                'nombre_etapes_terminees' => $etapes->where('terminee', true)->count(),
                                'quiz' => $c->quiz ? [
                                    'id' => $c->quiz->id,
                                    'titre' => $c->quiz->titre,
                                    'mode' => $c->quiz->mode?->value,
                                    'duree_limite' => $c->quiz->duree_limite,
                                    'seuil_reussite' => (float) $c->quiz->seuil_reussite,
                                    'verrouille' => ! $acces,
                                ] : null,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $cours->loadMissing('statut');

        return [
            'cours' => $cours,
            'inscription' => $inscription,
            'acces' => (bool) $acces,
            // Pourquoi le contenu est verrouillé, pour que l'application affiche le bon message.
            'raison_verrou' => $acces ? null : $this->raisonVerrou($abonnement),
            // Conservé pour compatibilité : il n'y a plus de lecture sans abonnement (règle 7).
            'lecture_historique_seule' => false,
            'essai' => $limite !== null,
            // Cours déjà publié en cours de revalidation : visible par ses inscrits seulement (règle 1).
            'mise_a_jour_en_cours' => $cours->statut?->value !== 'PUBLIE' && $cours->date_publication !== null,
            'modules' => $modules,
        ];
    }

    /** Ouvre un chapitre : crée la progression (premier accès) et retourne le chapitre avec ses blocs. */
    public function ouvrirChapitre(Utilisateur $utilisateur, Chapitre $chapitre): array
    {
        // Règle 7 : sans abonnement qui donne accès, aucun chapitre ne s'ouvre, même déjà lu.
        // contexte() vérifie le cours, l'abonnement, l'inscription et la limite de l'essai.
        $ctx = $this->contexte($utilisateur, $chapitre);
        $inscription = $ctx['inscription'];

        // Créer la progression au premier accès.
        $progression = ProgressionChapitre::firstOrCreate(
            [
                'inscription_cours_id' => $inscription->id,
                'chapitre_id' => $chapitre->id,
            ],
            [
                'date_debut' => now(),
                'est_termine' => false,
            ]
        );

        // Charger le contenu du chapitre.
        $chapitre->load([
            'blocs' => fn ($q) => $q->orderBy('ordre'),
            'blocs.texte',
            'blocs.image',
            'blocs.code',
            'quiz' => fn ($q) => $q->where('actif', true),
            'quiz.mode',
            'etapesProjetGuide' => fn ($q) => $q
                ->orderBy('ordre')
                ->with([
                    'projetGuide:id,titre',
                    'progressions' => fn ($p) => $p->where('inscription_cours_id', $inscription->id),
                ]),
        ]);

        $etapes = $chapitre->etapesProjetGuide->map(fn ($etape) => [
            'id' => $etape->id,
            'ordre' => $etape->ordre,
            'consigne' => $etape->consigne,
            'code_depart' => $etape->code_depart,
            'tests_attendus' => $etape->tests_attendus,
            'projet_guide' => [
                'id' => $etape->projetGuide?->id,
                'titre' => $etape->projetGuide?->titre,
            ],
            'terminee' => $etape->progressions->contains(fn ($p) => $p->est_terminee),
        ])->values();

        return [
            'chapitre' => $chapitre,
            'progression' => $progression,
            // Conservé pour compatibilité : un chapitre ouvert l'est toujours avec un abonnement (règle 7).
            'lecture_seule' => false,
            'etapes_projet_guide' => $etapes,
            'nombre_etapes' => $etapes->count(),
            'nombre_etapes_terminees' => $etapes->where('terminee', true)->count(),
        ];
    }

    /**
     * Termine un chapitre :
     * - toutes ses étapes de projet guidé sont terminées ;
     * - son quiz actif en mode EVALUATION, s'il existe, est réussi (un quiz ENTRAINEMENT n'est jamais exigé).
     * Les XP du chapitre sont attribués une seule fois, puis la progression et l'achèvement sont recalculés.
     */
    public function terminerChapitre(Utilisateur $utilisateur, Chapitre $chapitre): array
    {
        return DB::transaction(function () use ($utilisateur, $chapitre) {
            $ctx = $this->contexte($utilisateur, $chapitre);
            $inscription = $ctx['inscription'];

            // 1. Étapes du projet guidé.
            if (! $this->toutesEtapesTerminees($inscription, $chapitre)) {
                throw new RegleMetierException(
                    'Terminez toutes les étapes du projet guidé avant de terminer ce chapitre.',
                    422
                );
            }

            // 2. Quiz actif en mode EVALUATION, s'il existe : il doit être réussi.
            //    Un quiz en mode ENTRAINEMENT n'est jamais exigé.
            $chapitre->loadMissing('quiz.mode');
            $quiz = $chapitre->quiz;

            if ($quiz && $quiz->actif && $quiz->mode?->value === 'EVALUATION') {
                $quizReussi = TentativeQuiz::where('apprenant_id', $utilisateur->id)
                    ->where('quiz_id', $quiz->id)
                    ->whereHas('statut', fn ($q) => $q->where('value', 'REUSSI'))
                    ->exists();

                if (! $quizReussi) {
                    throw new RegleMetierException('Vous devez réussir le quiz d’évaluation avant de terminer ce chapitre.', 422);
                }
            }

            // 3. Progression du chapitre.
            $progression = ProgressionChapitre::firstOrCreate(
                ['inscription_cours_id' => $inscription->id, 'chapitre_id' => $chapitre->id],
                ['date_debut' => now(), 'est_termine' => false]
            );

            $xpGagne = 0;

            // 4. Terminer le chapitre et attribuer les XP une seule fois.
            if (! $progression->est_termine) {
                $progression->update(['est_termine' => true, 'date_fin' => now()]);

                $xpGagne = (int) $chapitre->xp_recompense;
                $this->gagnerXp($ctx['apprenant'], $xpGagne);
            }

            // 5. Recalculer la progression et vérifier l'achèvement du cours.
            $inscription = $this->recalculerProgression($inscription);
            $inscription = $this->verifierAchevement($inscription);

            return [
                'inscription' => $inscription->fresh(['statut', 'cours', 'certificat']),
                'xp_gagne' => $xpGagne,
                'apprenant' => Apprenant::findOrFail($utilisateur->id),
            ];
        });
    }

    /** Apprenant.gagnerXp() : cumule les points, recalcule le niveau et la série de jours. */
    public function gagnerXp(Apprenant $apprenant, int $points): Apprenant
    {
        $apprenant = Apprenant::whereKey($apprenant->id)->lockForUpdate()->firstOrFail();

        $xp = $apprenant->xp_total + max(0, $points);
        $aujourdhui = today();
        $derniere = $apprenant->date_derniere_activite
            ? Carbon::parse($apprenant->date_derniere_activite)->startOfDay()
            : null;

        if ($derniere && $derniere->isSameDay($aujourdhui)) {
            $serie = max(1, (int) $apprenant->serie_jours_consecutifs);
        } elseif ($derniere && $derniere->isSameDay($aujourdhui->copy()->subDay())) {
            $serie = (int) $apprenant->serie_jours_consecutifs + 1;
        } else {
            $serie = 1;
        }

        $apprenant->update([
            'xp_total' => $xp,
            'niveau_xp' => intdiv($xp, self::PALIER_XP) + 1,
            'serie_jours_consecutifs' => $serie,
            'date_derniere_activite' => $aujourdhui,
        ]);

        return $apprenant;
    }

    /** Progression = chapitres actifs terminés / chapitres actifs × 100 (figée si TERMINEE, règle 4). */
    public function recalculerProgression(InscriptionCours $inscription): InscriptionCours
    {
        $inscription->loadMissing('statut');

        if ($inscription->aStatut('TERMINEE')) {
            return $inscription;
        }

        $actifs = $this->acces->chapitreIdsActifs($inscription->cours_id);

        $pourcentage = $actifs->isEmpty() ? 0 : round(
            ProgressionChapitre::where('inscription_cours_id', $inscription->id)
                ->where('est_termine', true)->whereIn('chapitre_id', $actifs)->count() / $actifs->count() * 100,
            2
        );

        $inscription->update(['progression' => $pourcentage]);
        $this->parcours->recalculerPourInscriptionCours($inscription);

        return $inscription;
    }

    /**
     * Règle 4 : après une activation / désactivation de contenu, les inscriptions EN_COURS du cours
     * sont recalculées sur les chapitres actifs (et passent TERMINEE si tout le reste est fait).
     * Les inscriptions TERMINEE ne bougent pas : progression figée à 100, certificat acquis.
     */
    public function recalculerCours(int $coursId): void
    {
        InscriptionCours::where('cours_id', $coursId)
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->chunkById(100, function ($lot) {
                foreach ($lot as $inscription) {
                    $this->verifierAchevement($this->recalculerProgression($inscription));
                }
            });
    }

    /** InscriptionCours.estTermine() : tous les chapitres actifs terminés + quiz actifs EVALUATION réussis. */
   /* public function estTermine(InscriptionCours $inscription): bool
    {
        $actifs = $this->acces->chapitreIdsActifs($inscription->cours_id);

        if ($actifs->isEmpty()) {
            return false;
        }

        $termines = ProgressionChapitre::where('inscription_cours_id', $inscription->id)
            ->where('est_termine', true)->whereIn('chapitre_id', $actifs)->count();

        if ($termines < $actifs->count()) {
            return false;
        }

        $quizEvaluation = Quiz::where('actif', true)->whereIn('chapitre_id', $actifs)
            ->whereHas('mode', fn ($q) => $q->where('value', 'EVALUATION'))->pluck('id');

        if ($quizEvaluation->isEmpty()) {
            return true;
        }

        $reussis = TentativeQuiz::where('apprenant_id', $inscription->apprenant_id)
            ->whereIn('quiz_id', $quizEvaluation)
            ->whereHas('statut', fn ($q) => $q->where('value', 'REUSSI'))
            ->distinct()->count('quiz_id');

        return $reussis >= $quizEvaluation->count();
    }
   */

    /**
     * InscriptionCours.estTermine() : tous les chapitres actifs du cours sont terminés.
     *
     * Le quiz EVALUATION d'un chapitre est vérifié au moment où le chapitre se termine (terminerChapitre).
     * Un chapitre déjà terminé le reste : un quiz ajouté ou passé en EVALUATION après coup
     * n'est exigé que pour les chapitres que l'apprenant n'a pas encore terminés.
     */
    public function estTermine(InscriptionCours $inscription): bool
    {
        $actifs = $this->acces->chapitreIdsActifs($inscription->cours_id);

        if ($actifs->isEmpty()) {
            return false;
        }

        $termines = ProgressionChapitre::where('inscription_cours_id', $inscription->id)
            ->where('est_termine', true)
            ->whereIn('chapitre_id', $actifs)
            ->count();

        return $termines >= $actifs->count();
    }

    /** Passe l'inscription à TERMINEE (progression 100), délivre le certificat, met à jour les parcours. */
    public function verifierAchevement(InscriptionCours $inscription): InscriptionCours
    {
        $inscription->loadMissing('statut');

        if ($inscription->aStatut('TERMINEE') || ! $this->estTermine($inscription)) {
            return $inscription;
        }

        $inscription->update([
            'statut_id' => CodeList::idOf('StatutInscription', 'TERMINEE'),
            'progression' => 100,
        ]);

        $inscription = $inscription->fresh(['statut']);

        $this->certificats->delivrerSiEligible($inscription);
        $this->parcours->recalculerPourInscriptionCours($inscription);

        return $inscription;
    }

    /** Vérifie que toutes les étapes du projet guidé du chapitre sont terminées. */
    public function toutesEtapesTerminees(InscriptionCours $inscription, Chapitre $chapitre): bool
    {
        return ! $chapitre->etapesProjetGuide()
            ->whereDoesntHave('progressions', function ($query) use ($inscription) {
                $query->where('inscription_cours_id', $inscription->id)
                    ->where('est_terminee', true);
            })
            ->exists();
    }

    /** Raison du verrouillage, pour un message clair dans l'application. */
    private function raisonVerrou(?Abonnement $abonnement): string
    {
        $statut = $abonnement?->statut?->value;

        return match (true) {
            $abonnement === null => 'AUCUN_ABONNEMENT',
            $statut === 'EN_ATTENTE_PAIEMENT' => 'EN_ATTENTE_PAIEMENT',
            $statut === 'SUSPENDU' => 'SUSPENDU',
            default => 'PERIODE_TERMINEE',
        };
    }
}
