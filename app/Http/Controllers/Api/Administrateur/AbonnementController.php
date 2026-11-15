<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Http\Resources\AbonnementResource;
use App\Services\AbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AbonnementController extends Controller
{
    public function __construct(
        private AbonnementService $abonnementService
    ) {}

    /**
     * Liste des abonnements.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:255'],
            'statut' => [
                'nullable',
                'string',
                'in:EN_ATTENTE_PAIEMENT,ESSAI,ACTIF,SUSPENDU,RESILIE,EXPIRE',
            ],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $abonnements = $this->abonnementService->lister($filtres);

        return AbonnementResource::collection($abonnements);
    }

    /**
     * Détails d'un abonnement.
     */
    public function show(int $id): JsonResponse
    {
        $abonnement = $this->abonnementService->details($id);

        return response()->json([
            'message' => 'Détails de l’abonnement récupérés avec succès.',
            'data' => new AbonnementResource($abonnement),
        ]);
    }
}
