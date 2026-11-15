<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Http\Resources\ProjetResource;
use App\Services\ProjetService;
use Illuminate\Http\Request;

class ProjetController extends Controller
{
    public function __construct(
        private ProjetService $projetService
    ) {}

    /**
     * Lister les projets.
     */
    public function index(Request $request)
    {
        $projets = $this->projetService->lister(
            $request->only(['recherche', 'parcours_id']),
            $request->integer('par_page', 10)
        );

        return ProjetResource::collection($projets);
    }

    /**
     * Créer un projet dans un parcours.
     */
    public function store(
        StoreProjetRequest $request,
        int $parcours
    ) {
        $projet = $this->projetService->creer(
            $parcours,
            $request->validated()
        );

        return (new ProjetResource($projet))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Consulter un projet.
     */
    public function show(int $projet)
    {
        $resultat = $this->projetService->consulter($projet);

        return new ProjetResource($resultat);
    }

    /**
     * Modifier un projet.
     */
    public function update(
        UpdateProjetRequest $request,
        int $projet
    ) {
        $resultat = $this->projetService->modifier(
            $projet,
            $request->validated()
        );

        return new ProjetResource($resultat);
    }

    /**
     * Supprimer un projet.
     */
    public function destroy(int $projet)
    {
        $this->projetService->supprimer($projet);

        return response()->json([
            'message' => 'Le projet a été supprimé avec succès.',
        ]);
    }
}
