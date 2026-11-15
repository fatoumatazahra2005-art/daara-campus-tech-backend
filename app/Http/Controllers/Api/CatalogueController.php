<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourResource;
use App\Models\Cours;
use App\Services\CoursService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Catalogue : seuls les cours PUBLIE y apparaissent. */
class CatalogueController extends Controller
{
    public function __construct(private readonly CoursService $cours)
    {
    }

    /** Filtres : ?technologie=<id> ?niveau=DEBUTANT ?q=<mot du titre> ; tri du plus récemment publié au plus ancien. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $cours = Cours::with(['statut', 'niveau', 'technologie', 'auteur.utilisateur'])
            ->whereHas('statut', fn ($q) => $q->where('value', 'PUBLIE'))
            ->when($request->query('technologie'), fn ($q, $id) => $q->where('technologie_id', $id))
            ->when($request->query('niveau'), fn ($q, $niveau) => $q->whereHas('niveau', fn ($r) => $r->where('value', $niveau)))
            ->when($request->query('q'), fn ($q, $mot) => $q->where('titre', 'ilike', '%'.addcslashes($mot, '%_\\').'%'))
            ->orderByDesc('date_publication')
            ->orderByDesc('id')
            ->paginate(20);

        return CourResource::collection($cours);
    }

    /**
     * Fiche d'un cours : modules et chapitres seulement. Le contenu des chapitres (blocs, quiz)
     * est servi plus tard, avec le contrôle d'accès par abonnement.
     */
    public function show(Cours $cours): CourResource
    {
        Gate::authorize('view', $cours);

        return new CourResource($this->cours->charger($cours, false));
    }
}
