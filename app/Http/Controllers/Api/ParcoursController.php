<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParcoursRequest;
use App\Http\Requests\UpdateParcoursRequest;
use App\Http\Resources\ParcoursResource;
use App\Services\ParcoursService;
use Illuminate\Http\Request;

/** Gestion des parcours par l'administrateur. */
class ParcoursController extends Controller
{
    public function __construct(
        private ParcoursService $parcoursService
    ) {}

    /**
     * Lister les parcours : ?recherche=…&statut=BROUILLON|PUBLIE|ARCHIVE&page=…
     */
    public function index(Request $request)
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'statut'    => ['nullable', 'in:BROUILLON,PUBLIE,ARCHIVE'],
            'statut_id' => ['nullable', 'integer'],
            'par_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $parcours = $this->parcoursService->lister(
            $filtres,
            (int) ($filtres['par_page'] ?? 12)
        );

        return ParcoursResource::collection($parcours);
    }

    /**
     * Créer un parcours (en brouillon).
     */
    public function store(StoreParcoursRequest $request)
    {
        $parcours = $this->parcoursService->creer($request->validated());

        return (new ParcoursResource($parcours))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Consulter un parcours avec ses cours et ses projets.
     */
    public function show(int $parcours)
    {
        return new ParcoursResource($this->parcoursService->consulter($parcours));
    }

    /**
     * Modifier un parcours.
     * Avec une image, le front envoie POST + _method=PUT (multipart).
     */
    public function update(UpdateParcoursRequest $request, int $parcours)
    {
        $resultat = $this->parcoursService->modifier($parcours, $request->validated());

        return new ParcoursResource($resultat);
    }

    /**
     * Supprimer un parcours en brouillon.
     */
    public function destroy(int $parcours)
    {
        $this->parcoursService->supprimer($parcours);

        return response()->json([
            'message' => 'Le parcours a été supprimé avec succès.',
        ]);
    }

    /**
     * Publier un parcours : BROUILLON → PUBLIE.
     */
    public function publier(int $parcours)
    {
        return new ParcoursResource($this->parcoursService->publier($parcours));
    }

    /**
     * Archiver un parcours : PUBLIE → ARCHIVE (définitif).
     */
    public function archiver(int $parcours)
    {
        return new ParcoursResource($this->parcoursService->archiver($parcours));
    }
}
