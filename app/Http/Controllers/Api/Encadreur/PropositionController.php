<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\PropositionRequest;
use App\Http\Resources\PropositionResource;
use App\Models\Proposition;
use App\Models\Question;
use App\Services\CoursService;
use App\Services\EvaluationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PropositionController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly EvaluationsService $evaluation)
    {
        parent::__construct($coursService);
    }

    public function store(PropositionRequest $request, Question $question): JsonResponse
    {
        $this->autoriserSur($question);

        $proposition = $this->evaluation->ajouterProposition($question, $request->validated());

        return (new PropositionResource($proposition))->response()->setStatusCode(201);
    }

    public function update(PropositionRequest $request, Proposition $proposition): PropositionResource
    {
        $this->autoriserSur($proposition);

        return new PropositionResource($this->evaluation->modifierProposition($proposition, $request->validated()));
    }

    public function destroy(Proposition $proposition): Response
    {
        $this->autoriserSur($proposition);

        $this->evaluation->retirerProposition($proposition);

        return response()->noContent();
    }
}
