<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Apprenant;
use App\Models\CodeList;
use App\Models\InscriptionCours;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\ReponseApprenant;
use App\Models\TentativeQuiz;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Passage d'un quiz : démarrer, répondre, terminer, score, XP bonus.
 */
class QuizApprenantService
{
    public function __construct(private ProgressionService $progression)
    {
    }

    /**
     * Démarre le quiz : reprend la tentative en cours si elle existe,
     * sinon en crée une.
     */
    public function demarrer(Utilisateur $utilisateur, Quiz $quiz): TentativeQuiz
    {
        return DB::transaction(function () use ($utilisateur, $quiz) {

            // 1. Verrouiller l'apprenant pendant le démarrage du quiz.
            Apprenant::whereKey($utilisateur->id)
                ->lockForUpdate()
                ->firstOrFail();

            $quiz->loadMissing('chapitre', 'mode');

            if (! $quiz->actif) {
                throw new RegleMetierException('Quiz introuvable.', 404);
            }

            // 2. Vérifier l'accès de l'apprenant au chapitre.
            $ctx = $this->progression->contexte($utilisateur, $quiz->chapitre);

            // 3. Vérifier que toutes les étapes du projet guidé sont terminées.
            if (! $this->progression->toutesEtapesTerminees(
                $ctx['inscription'],
                $quiz->chapitre
            )) {
                throw new RegleMetierException(
                    'Terminez toutes les étapes du projet guidé avant de passer ce quiz.',
                    422
                );
            }

            // 4. Rechercher une tentative en cours.
            $enCours = TentativeQuiz::with('statut')
                ->where('apprenant_id', $utilisateur->id)
                ->where('quiz_id', $quiz->id)
                ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
                ->latest('id')
                ->first();

            if ($enCours) {

                // Si elle n'est pas expirée, on la retourne.
                if (! $this->estExpiree($enCours, $quiz)) {
                    return $enCours;
                }

                // Si elle est expirée, on la clôture.
                $this->cloturer($enCours);
            }

            // 5. Créer une nouvelle tentative.
            return TentativeQuiz::create([
                'date_debut' => now(),
                'statut_id' => CodeList::idOf('StatutTentative', 'EN_COURS'),
                'apprenant_id' => $utilisateur->id,
                'quiz_id' => $quiz->id,
            ])->load('statut');

        });
    }

    /**
     * Retourne les questions sans les bonnes réponses,
     * avec les réponses déjà données par l'apprenant.
     */
    public function questions(TentativeQuiz $tentative): array
    {
        $this->exigerNonExpiree($tentative);

        $reponses = ReponseApprenant::with('propositionsChoisies')
            ->where('tentative_id', $tentative->id)
            ->get()
            ->keyBy('question_id');

        return Question::with([
            'type',
            'propositions' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
        ])
            ->where('quiz_id', $tentative->quiz_id)
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(fn (Question $q) => [
                'id' => $q->id,
                'enonce' => $q->enonce,
                'type' => $q->type?->value,
                'points' => $q->points,
                'ordre' => $q->ordre,

                'propositions' => $q->propositions->map(fn ($p) => [
                    'id' => $p->id,
                    'texte' => $p->texte,
                    'ordre' => $p->ordre,
                ])->values()->all(),

                'reponse' => $reponses->get($q->id)
                        ?->propositionsChoisies
                        ->pluck('id')
                        ->values()
                        ->all() ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * Enregistre ou remplace la réponse à une question.
     * En mode ENTRAINEMENT, la correction est renvoyée immédiatement.
     * En mode EVALUATION, elle est révélée uniquement à la fin.
     *
     * @param list<int> $propositionIds
     */
    public function repondre(
        TentativeQuiz $tentative,
        Question $question,
        array $propositionIds
    ): array {
        $tentative->loadMissing('statut', 'quiz.mode');

        if (! $tentative->aStatut('EN_COURS')) {
            throw new RegleMetierException('Cette tentative est terminée.', 422);
        }

        $this->exigerNonExpiree($tentative);

        if (! $question->actif || (int) $question->quiz_id !== (int) $tentative->quiz_id) {
            throw new RegleMetierException(
                'Cette question n’appartient pas à ce quiz.',
                422
            );
        }

        $question->loadMissing('type', 'propositions');

        $actives = $question->propositions->where('actif', true);

        $ids = array_values(array_unique(array_map('intval', $propositionIds)));

        if (
            $ids === []
            || array_diff(
                $ids,
                $actives->pluck('id')->map(fn ($i) => (int) $i)->all()
            ) !== []
        ) {
            throw new RegleMetierException(
                'Les propositions choisies ne correspondent pas à cette question.',
                422
            );
        }

        if (
            in_array($question->type?->value, ['VRAI_FAUX', 'CHOIX_UNIQUE'], true)
            && count($ids) !== 1
        ) {
            throw new RegleMetierException(
                'Une seule proposition doit être choisie pour cette question.',
                422
            );
        }

        DB::transaction(function () use ($tentative, $question, $ids) {

            $reponse = ReponseApprenant::updateOrCreate(
                [
                    'tentative_id' => $tentative->id,
                    'question_id' => $question->id,
                ],
                [
                    'date_reponse' => now(),
                ]
            );

            // Enregistrer les propositions choisies.
            $reponse->propositionsChoisies()->sync($ids);
        });

        $resultat = [
            'enregistree' => true,
        ];

        if ($tentative->quiz->mode?->value === 'ENTRAINEMENT') {
            $resultat['est_correcte'] = $this->estBonneReponse($actives, $ids);

            $resultat['bonnes_propositions'] = $actives
                ->where('est_correcte', true)
                ->pluck('id')
                ->values()
                ->all();

            $resultat['explication'] = $question->explication;
        }

        return $resultat;
    }

    /**
     * Termine la tentative (idempotent) et retourne le résultat détaillé.
     */
    public function terminer(TentativeQuiz $tentative): array
    {
        DB::transaction(function () use ($tentative) {

            $t = TentativeQuiz::whereKey($tentative->id)
                ->lockForUpdate()
                ->with('statut', 'quiz.mode', 'quiz.chapitre')
                ->firstOrFail();

            if ($t->aStatut('EN_COURS')) {
                $this->cloturer($t);
            }
        });

        return $this->resultat(
            $tentative->fresh(['statut', 'quiz'])
        );
    }

    /**
     * Résultat d'une tentative terminée :
     * score, statut et correction question par question.
     */
    public function resultat(TentativeQuiz $tentative): array
    {
        $tentative->loadMissing('statut', 'quiz');

        if ($tentative->aStatut('EN_COURS')) {
            throw new RegleMetierException(
                'La correction est disponible une fois la tentative terminée.',
                422
            );
        }

        $reponses = ReponseApprenant::with('propositionsChoisies')
            ->where('tentative_id', $tentative->id)
            ->get()
            ->keyBy('question_id');

        $corrections = Question::with([
            'propositions' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
        ])
            ->where('quiz_id', $tentative->quiz_id)
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(function (Question $q) use ($reponses) {

                $choisies = $reponses->get($q->id)
                    ?->propositionsChoisies
                    ->pluck('id')
                    ->map(fn ($i) => (int) $i)
                    ->values()
                    ->all() ?? [];

                return [
                    'question_id' => $q->id,
                    'enonce' => $q->enonce,
                    'points' => $q->points,
                    'explication' => $q->explication,
                    'choisies' => $choisies,

                    'bonnes' => $q->propositions
                        ->where('est_correcte', true)
                        ->pluck('id')
                        ->map(fn ($i) => (int) $i)
                        ->values()
                        ->all(),

                    'correcte' => $choisies !== []
                        && $this->estBonneReponse($q->propositions, $choisies),
                ];
            })
            ->values()
            ->all();

        return [
            'tentative' => $tentative,
            'seuil_reussite' => (float) $tentative->quiz->seuil_reussite,
            'corrections' => $corrections,
        ];
    }

    /**
     * Historique des tentatives de l'apprenant pour un quiz.
     */
    public function historique(Utilisateur $utilisateur, Quiz $quiz)
    {
        return TentativeQuiz::with('statut')
            ->where('apprenant_id', $utilisateur->id)
            ->where('quiz_id', $quiz->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Clôture automatique des tentatives dont la durée limite est dépassée.
     */
    public function cloturerExpirees(): int
    {
        $n = 0;

        TentativeQuiz::with('quiz')
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->whereHas('quiz', fn ($q) => $q->whereNotNull('duree_limite'))
            ->chunkById(100, function ($lot) use (&$n) {

                foreach ($lot as $t) {

                    if ($this->estExpiree($t, $t->quiz)) {

                        DB::transaction(fn () => $this->cloturer(
                            TentativeQuiz::whereKey($t->id)
                                ->lockForUpdate()
                                ->with('statut', 'quiz.mode', 'quiz.chapitre.module')
                                ->firstOrFail()
                        ));

                        $n++;
                    }
                }
            });

        return $n;
    }

    /**
     * Calcule le score, fixe REUSSI/ECHOUE et crédite le bonus XP
     * à la première réussite uniquement.
     */
    private function cloturer(TentativeQuiz $tentative): void
    {
        DB::transaction(function () use ($tentative) {

            // 1. Relire et verrouiller la tentative.
            $tentative = TentativeQuiz::with('statut')
                ->lockForUpdate()
                ->findOrFail($tentative->getKey());

            // Si elle est déjà clôturée, ne rien faire.
            if (! $tentative->aStatut('EN_COURS')) {
                return;
            }

            $tentative->load('quiz.chapitre.module');

            $quiz = $tentative->quiz;

            // 2. Calculer le score et déterminer le résultat.
            $score = $this->calculerScore($tentative);

            $reussi = $score >= (float) $quiz->seuil_reussite;

            // 3. Déterminer la date de fin.
            $fin = now();

            if ($quiz->duree_limite) {

                $limite = $tentative->date_debut->copy()
                    ->addMinutes((int) $quiz->duree_limite);

                if ($fin->gt($limite)) {
                    $fin = $limite;
                }
            }

            // 4. Verrouiller l'apprenant pour sécuriser le bonus XP.
            $apprenant = Apprenant::whereKey($tentative->apprenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 5. Vérifier s'il existe déjà une réussite pour ce quiz.
            $reussitePrecedente = TentativeQuiz::where(
                'apprenant_id',
                $tentative->apprenant_id
            )
                ->where('quiz_id', $quiz->id)
                ->where('id', '!=', $tentative->id)
                ->whereHas('statut', fn ($q) => $q->where('value', 'REUSSI'))
                ->exists();

            // 6. Déterminer si cette réussite est la première.
            $premiereReussite = $reussi && ! $reussitePrecedente;

            // 7. Clôturer la tentative.
            $tentative->update([
                'date_fin' => $fin,
                'score' => $score,
                'statut_id' => CodeList::idOf(
                    'StatutTentative',
                    $reussi ? 'REUSSI' : 'ECHOUE'
                ),
            ]);

            // 8. Attribuer le bonus uniquement à la première réussite.
            if ($premiereReussite && $quiz->xp_bonus > 0) {

                $this->progression->gagnerXp(
                    $apprenant,
                    (int) $quiz->xp_bonus
                );
            }

            // 9. Vérifier si le cours est terminé après une réussite.
            if ($reussi) {

                $inscription = InscriptionCours::where(
                    'apprenant_id',
                    $tentative->apprenant_id
                )
                    ->where('cours_id', $quiz->chapitre->module->cours_id)
                    ->first();

                if ($inscription) {
                    $this->progression->verifierAchevement($inscription);
                }
            }
        });
    }

    /**
     * Score = points des questions entièrement bien répondues
     * / total des points × 100.
     */
    private function calculerScore(TentativeQuiz $tentative): float
    {
        $questions = Question::with([
            'propositions' => fn ($q) => $q->where('actif', true),
        ])
            ->where('quiz_id', $tentative->quiz_id)
            ->where('actif', true)
            ->get();

        $total = $questions->sum('points');

        if ($total <= 0) {
            return 0.0;
        }

        $reponses = ReponseApprenant::with('propositionsChoisies')
            ->where('tentative_id', $tentative->id)
            ->get()
            ->keyBy('question_id');

        $obtenus = 0;

        foreach ($questions as $question) {

            $reponse = $reponses->get($question->id);

            if (
                $reponse
                && $this->estBonneReponse(
                    $question->propositions,
                    $reponse->propositionsChoisies->pluck('id')->all()
                )
            ) {
                $obtenus += $question->points;
            }
        }

        return round($obtenus / $total * 100, 2);
    }

    /**
     * Les propositions cochées doivent être exactement
     * les propositions correctes.
     */
    private function estBonneReponse($propositionsActives, array $choisies): bool
    {
        $bonnes = $propositionsActives
            ->where('est_correcte', true)
            ->pluck('id')
            ->map(fn ($i) => (int) $i)
            ->sort()
            ->values()
            ->all();

        $choisies = collect($choisies)
            ->map(fn ($i) => (int) $i)
            ->sort()
            ->values()
            ->all();

        return $bonnes !== [] && $bonnes === $choisies;
    }

    /**
     * Vérifie si une tentative a dépassé sa durée limite.
     */
    private function estExpiree(TentativeQuiz $tentative, Quiz $quiz): bool
    {
        return $quiz->duree_limite
            && now()->greaterThan(
                $tentative->date_debut->copy()
                    ->addMinutes((int) $quiz->duree_limite)
            );
    }

    /**
     * Clôture la tentative si elle est expirée,
     * puis refuse l'action demandée.
     */
    private function exigerNonExpiree(TentativeQuiz $tentative): void
    {
        $tentative->loadMissing('statut', 'quiz');

        if (
            $tentative->aStatut('EN_COURS')
            && $this->estExpiree($tentative, $tentative->quiz)
        ) {

            DB::transaction(fn () => $this->cloturer(
                TentativeQuiz::whereKey($tentative->id)
                    ->lockForUpdate()
                    ->with('statut', 'quiz.mode', 'quiz.chapitre.module')
                    ->firstOrFail()
            ));

            throw new RegleMetierException(
                'Le temps imparti est écoulé : la tentative a été clôturée.',
                422
            );
        }
    }
}
