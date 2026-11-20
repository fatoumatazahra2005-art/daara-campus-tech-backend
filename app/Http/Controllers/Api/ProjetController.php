<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjetRequest;
use App\Http\Requests\UpdateProjetRequest;
use App\Http\Resources\ProjetResource;
use App\Services\ProjetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Projets finaux des parcours (administrateur).
 * Règle 29 : un projet déjà attribué n'est pas supprimé mais désactivé ; ses rendus sont conservés.
 */
class ProjetController extends Controller
{
    public function __construct(
        private ProjetService $projetService
    ) {}

    /**
     * Lister les projets : ?recherche=…&parcours_id=…&actif=0|1
     */
    public function index(Request $request)
    {
        $projets = $this->projetService->lister(
            $request->only(['recherche', 'parcours_id', 'actif']),
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
     * Retirer un projet : suppression réelle s'il n'a été attribué à personne,
     * sinon désactivation (ses rendus sont conservés).
     */
    public function destroy(int $projet): JsonResponse
    {
        $resultat = $this->projetService->supprimer($projet);

        if ($resultat === 'desactive') {
            return response()->json([
                'message' => 'Ce projet a déjà été attribué à des apprenants : il a été désactivé et ses rendus sont conservés.',
                'resultat' => 'desactive',
                'data' => new ProjetResource($this->projetService->consulter($projet)),
            ]);
        }

        return response()->json([
            'message' => 'Le projet a été supprimé avec succès.',
            'resultat' => 'supprime',
        ]);
    }

    /**
     * Projet.activer() : le projet est de nouveau attribué et exigé.
     */
    public function activer(int $projet): JsonResponse
    {
        $resultat = $this->projetService->activer($projet);

        return response()->json([
            'message' => 'Le projet est de nouveau actif : il est attribué aux inscrits qui ont terminé les cours obligatoires.',
            'data' => new ProjetResource($resultat),
        ]);
    }

    /**
     * Projet.desactiver() : le projet n'est plus attribué ni exigé ; ses rendus sont conservés.
     */
    public function desactiver(int $projet): JsonResponse
    {
        $resultat = $this->projetService->desactiver($projet);

        return response()->json([
            'message' => 'Le projet est désactivé : il n’est plus attribué ni exigé, ses rendus sont conservés.',
            'data' => new ProjetResource($resultat),
        ]);
    }
}
