<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Chapitre;
use App\Models\CodeList;
use App\Models\Proposition;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\Apprenant\ProgressionService;
use Illuminate\Support\Facades\DB;

/**
 * Quiz d'un chapitre (un seul par chapitre), questions et propositions.
 *
 * Règle 1 : seule une vraie modification fait repasser un cours publié en validation (wasChanged).
 * Règle 3 : sur un cours déjà publié, retirer un quiz, une question ou une proposition le désactive,
 *           avec les mêmes contrôles que l'interrupteur (ActivationContenuService).
 * Règle 4 : un quiz ajouté, ou dont le mode change, modifie ce qu'il faut réussir pour terminer le cours :
 *           les inscriptions EN_COURS sont recalculées (les inscriptions TERMINEE restent acquises).
 */
class EvaluationsService
{
    public function __construct(
        private readonly CoursService $cours,
        private readonly ActivationContenuService $activation,
        private readonly ProgressionService $progression,
    ) {
    }

    // ------------------------------------------------------------------ Quiz
    public function creerQuiz(Chapitre $chapitre, array $donnees): Quiz
    {
        $quiz = DB::transaction(function () use ($chapitre, $donnees) {
            $existant = Quiz::where('chapitre_id', $chapitre->id)->first();

            if ($existant?->actif) {
                throw new RegleMetierException('Ce chapitre a déjà un quiz.');
            }

            $champs = [
                'titre' => $donnees['titre'],
                'duree_limite' => $donnees['duree_limite'] ?? null,
                'mode_id' => CodeList::idOf('ModeQuiz', $donnees['mode']),
                'seuil_reussite' => $donnees['seuil_reussite'] ?? 50,
                'xp_bonus' => $donnees['xp_bonus'] ?? 0,
            ];

            if ($existant) {
                // un quiz retiré après publication est réactivé plutôt que recréé (chapitre_id est unique)
                $existant->update($champs + ['actif' => true]);
                $quiz = $existant;
            } else {
                $quiz = Quiz::create($champs + [
                        'date_creation' => now()->toDateString(),
                        'chapitre_id' => $chapitre->id,
                    ]);
            }

            $this->cours->marquerModifie($this->cours->coursDe($chapitre));

            return $quiz->refresh();
        });

        // Règle 4 : un nouveau quiz peut devenir exigé pour terminer le cours.
        $this->recalculerSiPublie($quiz);

        return $quiz;
    }

    public function modifierQuiz(Quiz $quiz, array $donnees): Quiz
    {
        [$quiz, $modeChange] = DB::transaction(function () use ($quiz, $donnees) {
            $champs = array_intersect_key($donnees, array_flip(['titre', 'duree_limite', 'seuil_reussite', 'xp_bonus']));

            if (isset($donnees['mode'])) {
                $champs['mode_id'] = CodeList::idOf('ModeQuiz', $donnees['mode']);
            }

            $quiz->update($champs);

            // Mêmes données renvoyées : pas de modification, pas de revalidation.
            if ($quiz->wasChanged()) {
                $this->cours->marquerModifie($this->cours->coursDe($quiz));
            }

            return [$quiz->refresh(), $quiz->wasChanged('mode_id')];
        });

        // ENTRAINEMENT ↔ EVALUATION : le quiz devient exigé (ou ne l'est plus) pour terminer le cours.
        if ($modeChange) {
            $this->recalculerSiPublie($quiz);
        }

        return $quiz;
    }

    public function retirerQuiz(Quiz $quiz): void
    {
        $this->retirer($quiz);
    }

    // ------------------------------------------------------------------ Questions
    /** @param array{enonce: string, explication?: ?string, type: string, points?: int, propositions?: list<array{texte: string, est_correcte?: bool}>} $donnees */
    public function ajouterQuestion(Quiz $quiz, array $donnees): Question
    {
        return DB::transaction(function () use ($quiz, $donnees) {
            $question = Question::create([
                'enonce' => $donnees['enonce'],
                'explication' => $donnees['explication'] ?? null,
                'type_id' => CodeList::idOf('TypeQuestion', $donnees['type']),
                'points' => $donnees['points'] ?? 1,
                'ordre' => (int) Question::where('quiz_id', $quiz->id)->max('ordre') + 1,
                'quiz_id' => $quiz->id,
            ]);

            foreach (array_values($donnees['propositions'] ?? []) as $i => $proposition) {
                Proposition::create([
                    'texte' => $proposition['texte'],
                    'est_correcte' => $proposition['est_correcte'] ?? false,
                    'ordre' => $i + 1,
                    'question_id' => $question->id,
                ]);
            }

            $this->cours->marquerModifie($this->cours->coursDe($quiz));

            return $question->refresh()->load(['type', 'propositions']);
        });
    }

    public function modifierQuestion(Question $question, array $donnees): Question
    {
        return DB::transaction(function () use ($question, $donnees) {
            $champs = array_intersect_key($donnees, array_flip(['enonce', 'explication', 'points']));

            if (isset($donnees['type'])) {
                $champs['type_id'] = CodeList::idOf('TypeQuestion', $donnees['type']);
            }

            $question->update($champs);

            if ($question->wasChanged()) {
                $this->cours->marquerModifie($this->cours->coursDe($question));
            }

            return $question->refresh()->load(['type', 'propositions']);
        });
    }

    public function retirerQuestion(Question $question): void
    {
        $this->retirer($question);
    }

    // ------------------------------------------------------------------ Propositions
    public function ajouterProposition(Question $question, array $donnees): Proposition
    {
        return DB::transaction(function () use ($question, $donnees) {
            $proposition = Proposition::create([
                'texte' => $donnees['texte'],
                'est_correcte' => $donnees['est_correcte'] ?? false,
                'ordre' => (int) Proposition::where('question_id', $question->id)->max('ordre') + 1,
                'question_id' => $question->id,
            ]);

            $this->cours->marquerModifie($this->cours->coursDe($question));

            return $proposition->refresh();
        });
    }

    public function modifierProposition(Proposition $proposition, array $donnees): Proposition
    {
        return DB::transaction(function () use ($proposition, $donnees) {
            $proposition->update(array_intersect_key($donnees, array_flip(['texte', 'est_correcte'])));

            if ($proposition->wasChanged()) {
                $this->cours->marquerModifie($this->cours->coursDe($proposition));
            }

            return $proposition->refresh();
        });
    }

    public function retirerProposition(Proposition $proposition): void
    {
        $this->retirer($proposition);
    }

    // ------------------------------------------------------------------ Outils internes

    /**
     * Règle 3 : cours jamais publié → suppression réelle ;
     * cours déjà publié → désactivation avec les contrôles de l'interrupteur et le recalcul des inscrits.
     */
    private function retirer(Quiz|Question|Proposition $element): void
    {
        $cours = $this->cours->coursDe($element);

        if ($cours->date_publication !== null) {
            $this->activation->desactiver($element);

            return;
        }

        DB::transaction(function () use ($element, $cours) {
            $this->cours->retirer($element, $cours);
        });
    }

    /** Règle 4 : recalcul des inscriptions EN_COURS, seulement si le cours a déjà été publié (il a des inscrits). */
    private function recalculerSiPublie(Quiz $quiz): void
    {
        $cours = $this->cours->coursDe($quiz);

        if ($cours->date_publication !== null) {
            $this->progression->recalculerCours($cours->id);
        }
    }
}
