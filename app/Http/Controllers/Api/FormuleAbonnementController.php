<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormuleAbonnementRequest;
use App\Http\Requests\UpdateFormuleAbonnementRequest;
use App\Http\Resources\FormuleAbonnementResource;
use App\Models\FormuleAbonnement;
use App\Services\FormuleAbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormuleAbonnementController extends Controller
{
    public function __construct(
        private FormuleAbonnementService $formuleService
    ) {}

    /**
     * Lister les formules.
     */
    public function index(Request $request)
    {
        $formules = $this->formuleService->lister(
            $request->only([
                'recherche',
                'periodicite_id',
                'active',
            ]),
            $request->integer('par_page', 10)
        );

        return FormuleAbonnementResource::collection($formules);
    }

    /**
     * Créer une formule.
     */
    public function store(
        StoreFormuleAbonnementRequest $request
    ): JsonResponse {

        $formule = $this->formuleService->creer(
            $request->validated()
        );

        return (new FormuleAbonnementResource($formule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Consulter une formule.
     */
    public function show(int $id): FormuleAbonnementResource
    {
        $formule = $this->formuleService->consulter($id);

        return new FormuleAbonnementResource($formule);
    }

    /**
     * Modifier une formule.
     */
    public function update(
        UpdateFormuleAbonnementRequest $request,
        FormuleAbonnement $formule
    ): FormuleAbonnementResource {

        $formule = $this->formuleService->modifier(
            $formule,
            $request->validated()
        );

        return new FormuleAbonnementResource($formule);
    }

    /**
     * Supprimer une formule.
     */
    public function destroy(
        FormuleAbonnement $formule
    ): JsonResponse {

        $this->formuleService->supprimer($formule);

        return response()->json([
            'message' => 'Formule supprimée avec succès.',
        ]);
    }
}
