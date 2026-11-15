<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\CoursRequest;
use App\Http\Resources\CourResource;
use App\Models\Cours;
use App\Services\CoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/** Encadreur.creerCours() et soumettreCours(). */
class CoursController extends Controller
{
    public function __construct(private readonly CoursService $cours)
    {
    }

    /** Mes cours, avec filtre ?statut=BROUILLON|EN_ATTENTE_VALIDATION|PUBLIE|REFUSE|ARCHIVE. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $cours = Cours::with(['statut', 'niveau', 'technologie'])
            ->where('auteur_id', $request->user()->id)
            ->when($request->query('statut'), fn ($q, $statut) => $q->whereHas('statut', fn ($r) => $r->where('value', $statut)))
            ->orderByDesc('id')
            ->paginate(20);

        return CourResource::collection($cours);
    }

    public function store(CoursRequest $request): JsonResponse
    {
        Gate::authorize('create', Cours::class);

        $cours = $this->cours->creer($request->user(), $request->validated());

        return (new CourResource($this->cours->charger($cours)))->response()->setStatusCode(201);
    }

    /** Contenu complet, y compris les bonnes réponses des quiz et le motif de refus. */
    public function show(Cours $cours): CourResource
    {
        Gate::authorize('voirContenu', $cours);

        return new CourResource($this->cours->charger($cours, true, true));
    }

    public function update(CoursRequest $request, Cours $cours): CourResource
    {
        Gate::authorize('update', $cours);

        return new CourResource($this->cours->charger($this->cours->modifier($cours, $request->validated())));
    }

    public function destroy(Cours $cours): Response
    {
        Gate::authorize('supprimer', $cours);

        $this->cours->supprimer($cours);

        return response()->noContent();
    }

    public function soumettre(Cours $cours): CourResource
    {
        Gate::authorize('soumettre', $cours);

        return new CourResource($this->cours->charger($this->cours->soumettre($cours), false));
    }

    public function archiver(Cours $cours): CourResource
    {
        Gate::authorize('archiver', $cours);

        return new CourResource($this->cours->charger($this->cours->archiver($cours), false));
    }
}
