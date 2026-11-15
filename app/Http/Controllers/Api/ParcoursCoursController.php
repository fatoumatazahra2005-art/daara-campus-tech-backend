<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AjouterCoursParcoursRequest;
use App\Http\Requests\ModifierCoursParcoursRequest;
use App\Http\Resources\ParcoursResource;
use App\Services\ParcoursCoursService;

class ParcoursCoursController extends Controller
{
    public function __construct(
        private ParcoursCoursService $parcoursCoursService
    ) {}

    /**
     * Ajouter plusieurs cours à un parcours.
     */
    public function ajouter(
        AjouterCoursParcoursRequest $request,
        string $parcours
    ) {
        $resultat = $this->parcoursCoursService->ajouter(
            (int) $parcours,
            $request->validated()['cours']
        );

        return new ParcoursResource($resultat);
    }

    /**
     * Retirer un cours d'un parcours.
     */
    public function retirer(int $parcours, int $cours)
    {
        $resultat = $this->parcoursCoursService->retirer(
            $parcours,
            $cours
        );

        return new ParcoursResource($resultat);
    }

    /**
     * Modifier un cours dans un parcours.
     */
    public function modifier(
        ModifierCoursParcoursRequest $request,
        int $parcours,
        int $cours
    ) {
        $resultat = $this->parcoursCoursService->modifier(
            $parcours,
            $cours,
            $request->validated()
        );

        return new ParcoursResource($resultat);
    }
}
