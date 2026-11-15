<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\ChapitreRequest;
use App\Http\Resources\ChapitreResource;
use App\Models\Chapitre;
use App\Models\Module;
use App\Services\ContenuCoursService;
use App\Services\CoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ChapitreController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly ContenuCoursService $contenu)
    {
        parent::__construct($coursService);
    }

    public function store(ChapitreRequest $request, Module $module): JsonResponse
    {
        $this->autoriserSur($module);

        $chapitre = $this->contenu->ajouterChapitre($module, $request->validated());

        return (new ChapitreResource($chapitre))->response()->setStatusCode(201);
    }

    public function update(ChapitreRequest $request, Chapitre $chapitre): ChapitreResource
    {
        $this->autoriserSur($chapitre);

        return new ChapitreResource($this->contenu->modifierChapitre($chapitre, $request->validated()));
    }

    public function destroy(Chapitre $chapitre): Response
    {
        $this->autoriserSur($chapitre);

        $this->contenu->retirerChapitre($chapitre);

        return response()->noContent();
    }
}
