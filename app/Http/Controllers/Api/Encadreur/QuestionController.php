<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\QuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\CoursService;
use App\Services\EvaluationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class QuestionController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly EvaluationsService $evaluation)
    {
        parent::__construct($coursService);
    }

    public function store(QuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $this->autoriserSur($quiz);

        $question = $this->evaluation->ajouterQuestion($quiz, $request->validated());

        return (new QuestionResource($question))->response()->setStatusCode(201);
    }

    public function update(QuestionRequest $request, Question $question): QuestionResource
    {
        $this->autoriserSur($question);

        return new QuestionResource($this->evaluation->modifierQuestion($question, $request->validated()));
    }

    public function destroy(Question $question): Response
    {
        $this->autoriserSur($question);

        $this->evaluation->retirerQuestion($question);

        return response()->noContent();
    }
}
