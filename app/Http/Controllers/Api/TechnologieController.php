<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technologie\TechnologieRequest;
use App\Http\Resources\Technologie\TechnologieResource;
use App\Models\Technologie;
use App\Services\TechnologieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TechnologieController extends Controller
{
    public function __construct(
        private TechnologieService $service
    ) {}

    // Lister les technologies
   /* public function index()
    {
        Gate::authorize('viewAny', Technologie::class);

        return TechnologieResource::collection(
            $this->service->lister()
        );
    }
*/
    // Lister les technologies (accès public)
    public function index()
    {
        return TechnologieResource::collection(
            $this->service->lister()
        );
    }

    // Consulter une technologie
    public function show(Technologie $technologie)
    {
        Gate::authorize('view', $technologie);

        return new TechnologieResource($technologie);
    }

    // Créer une technologie
    public function store(TechnologieRequest $request)
    {
        Gate::authorize('create', Technologie::class);

        $technologie = $this->service->creer(
            $request->validated()
        );

        return (new TechnologieResource($technologie))
            ->response()
            ->setStatusCode(201);
    }

    // Modifier une technologie
    public function update(
        TechnologieRequest $request,
        Technologie $technologie
    ) {
        Gate::authorize('update', $technologie);

        $technologie = $this->service->modifier(
            $technologie,
            $request->validated()
        );

        return new TechnologieResource($technologie);
    }

    // Supprimer une technologie
    public function destroy(Technologie $technologie)
    {
        Gate::authorize('delete', $technologie);

        $this->service->supprimer($technologie);

        return response()->json([
            'message' => 'Technologie supprimée avec succès.'
        ]);
    }
}
