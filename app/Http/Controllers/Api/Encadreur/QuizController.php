<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Exceptions\RegleMetierException;
use App\Http\Requests\Cours\QuizRequest;
use App\Http\Resources\QuizResource;
use App\Models\Chapitre;
use App\Models\Proposition;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\CoursService;
use App\Services\EvaluationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class QuizController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly EvaluationsService $evaluation)
    {
        parent::__construct($coursService);
    }

    public function store(QuizRequest $request, Chapitre $chapitre): JsonResponse
    {
        $this->autoriserSur($chapitre);

        $quiz = $this->evaluation->creerQuiz($chapitre, $request->validated());

        return (new QuizResource($quiz->load('mode')))->response()->setStatusCode(201);
    }

    /**
     * Quiz du chapitre pour l'encadreur : actif ou non, avec TOUTES ses questions et propositions
     * (actives et désactivées), pour pouvoir les réactiver. L'apprenant, lui, ne reçoit que les éléments actifs.
     * Lecture directe dans les tables : indépendante d'un éventuel filtre « actif » posé sur les relations.
     */
    public function show(Chapitre $chapitre): QuizResource
    {
        $this->autoriserSur($chapitre);

        $quiz = Quiz::with('mode')
            ->where('chapitre_id', $chapitre->id)
            ->orderByDesc('id')
            ->first();

        if (! $quiz) {
            throw new RegleMetierException('Ce chapitre n’a pas encore de quiz.', 404);
        }

        $questions = Question::with('type')
            ->where('quiz_id', $quiz->id)
            ->orderBy('id')
            ->get();

        $propositions = Proposition::whereIn('question_id', $questions->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('question_id');

        $questions->each(fn (Question $question) => $question->setRelation(
            'propositions',
            $propositions->get($question->id, collect())->values()
        ));

        $quiz->setRelation('questions', $questions);

        return new QuizResource($quiz);
    }

    public function update(QuizRequest $request, Quiz $quiz): QuizResource
    {
        $this->autoriserSur($quiz);

        return new QuizResource($this->evaluation->modifierQuiz($quiz, $request->validated())->load('mode'));
    }

    public function destroy(Quiz $quiz): Response
    {
        $this->autoriserSur($quiz);

        $this->evaluation->retirerQuiz($quiz);

        return response()->noContent();
    }
}
