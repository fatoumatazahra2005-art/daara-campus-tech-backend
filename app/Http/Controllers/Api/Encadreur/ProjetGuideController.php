<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\EtapeRequest;
use App\Http\Requests\Cours\ProjetGuideRequest;
use App\Http\Resources\EtapeResource;
use App\Http\Resources\ProjetGuideResource;
use App\Models\Cours;
use App\Models\EtapeProjetGuide;
use App\Models\ProjetGuide;
use App\Services\CoursService;
use App\Services\ProjetGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Le projet guidé fait partie de la création du cours : mêmes droits que le cours. */
class ProjetGuideController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly ProjetGuideService $projets)
    {
        parent::__construct($coursService);
    }

    public function store(ProjetGuideRequest $request, Cours $cours): JsonResponse
    {
        Gate::authorize('update', $cours);

        $projet = $this->projets->creer($cours, $request->validated());

        return (new ProjetGuideResource($projet))->response()->setStatusCode(201);
    }

    public function update(ProjetGuideRequest $request, ProjetGuide $projetGuide): ProjetGuideResource
    {
        $this->autoriserSur($projetGuide);

        return new ProjetGuideResource($this->projets->modifier($projetGuide, $request->validated()));
    }

    public function destroy(ProjetGuide $projetGuide): Response
    {
        $this->autoriserSur($projetGuide);

        $this->projets->supprimer($projetGuide);

        return response()->noContent();
    }

    public function storeEtape(EtapeRequest $request, ProjetGuide $projetGuide): JsonResponse
    {
        $this->autoriserSur($projetGuide);

        $etape = $this->projets->ajouterEtape($projetGuide, $request->validated());

        return (new EtapeResource($etape))->response()->setStatusCode(201);
    }

    public function updateEtape(EtapeRequest $request, EtapeProjetGuide $etape): EtapeResource
    {
        $this->autoriserSur($etape);

        return new EtapeResource($this->projets->modifierEtape($etape, $request->validated()));
    }

    public function destroyEtape(EtapeProjetGuide $etape): Response
    {
        $this->autoriserSur($etape);

        $this->projets->retirerEtape($etape);

        return response()->noContent();
    }
}
