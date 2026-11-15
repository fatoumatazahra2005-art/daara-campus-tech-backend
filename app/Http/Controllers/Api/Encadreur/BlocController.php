<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\BlocRequest;
use App\Http\Resources\BlocResource;
use App\Models\BlocContenu;
use App\Models\Chapitre;
use App\Services\ContenuCoursService;
use App\Services\CoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BlocController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly ContenuCoursService $contenu)
    {
        parent::__construct($coursService);
    }

    public function store(BlocRequest $request, Chapitre $chapitre): JsonResponse
    {
        $this->autoriserSur($chapitre);

        $bloc = $this->contenu->ajouterBloc($chapitre, $request->validated());

        return (new BlocResource($bloc))->response()->setStatusCode(201);
    }

    public function update(BlocRequest $request, BlocContenu $bloc): BlocResource
    {
        $this->autoriserSur($bloc);

        return new BlocResource($this->contenu->modifierBloc($bloc, $request->validated()));
    }

    public function destroy(BlocContenu $bloc): Response
    {
        $this->autoriserSur($bloc);

        $this->contenu->retirerBloc($bloc);

        return response()->noContent();
    }
}
