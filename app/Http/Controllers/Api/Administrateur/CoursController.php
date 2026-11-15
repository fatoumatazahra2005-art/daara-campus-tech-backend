<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cours\RefusCoursRequest;
use App\Http\Resources\CourResource;
use App\Models\Cours;
use App\Services\CoursService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Administrateur.validerCours(), refuserCours(motif), archivage et relecture des cours modifiés. */
class CoursController extends Controller
{
    public function __construct(private readonly CoursService $cours)
    {
    }

    /**
     * Cours à examiner : ?statut=…&recherche=…&a_relire=1
     * Sans statut : tous les cours sauf les brouillons.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $statutsAutorises = ['EN_ATTENTE_VALIDATION', 'PUBLIE', 'ARCHIVE', 'REFUSE'];

        $query = Cours::with(['statut', 'niveau', 'langue', 'technologie', 'auteur.utilisateur']);

        // ---------- Statut ----------
        $statut = $request->query('statut');

        if ($request->filled('statut')) {
            if (!in_array($statut, $statutsAutorises, true)) {
                abort(422, 'Statut de cours invalide.');
            }
            $query->whereHas('statut', fn ($q) => $q->where('value', $statut));
        } else {
            // Les brouillons restent privés à l'encadreur.
            $query->whereHas('statut', fn ($q) => $q->whereIn('value', $statutsAutorises));
        }

        // ---------- Cours publiés modifiés depuis la dernière relecture ----------
        $aRelire = $request->boolean('a_relire');
        if ($aRelire) {
            $query->where('a_relire', true);
        }

        // ---------- Recherche : titre ou nom/prénom de l'auteur ----------
        if ($request->filled('recherche')) {
            $terme = '%' . trim($request->query('recherche')) . '%';

            $query->where(function ($q) use ($terme) {
                $q->where('titre', 'ilike', $terme)
                    ->orWhereHas('auteur.utilisateur', function ($u) use ($terme) {
                        $u->where('nom', 'ilike', $terme)->orWhere('prenom', 'ilike', $terme);
                    });
            });
        }

        // ---------- Ordre ----------
        if ($aRelire) {
            $query->orderBy('modifie_le');                       // plus ancienne modification d'abord
        } elseif ($statut === 'EN_ATTENTE_VALIDATION') {
            $query->orderBy('updated_at')->orderBy('id');        // premier soumis, premier examiné
        } else {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        }

        return CourResource::collection($query->paginate(20)->withQueryString());
    }

    /** Contenu complet pour examen avant décision. */
    public function show(Cours $cours): CourResource
    {
        Gate::authorize('voirContenu', $cours);

        return new CourResource($this->cours->charger($cours));
    }

    public function valider(Request $request, Cours $cours): CourResource
    {
        Gate::authorize('valider', $cours);

        return new CourResource($this->cours->charger($this->cours->valider($cours, $request->user()), false));
    }

    public function refuser(RefusCoursRequest $request, Cours $cours): CourResource
    {
        Gate::authorize('refuser', $cours);

        $cours = $this->cours->refuser($cours, $request->user(), $request->validated('motif'));

        return new CourResource($this->cours->charger($cours, false));
    }

    public function archiver(Cours $cours): CourResource
    {
        Gate::authorize('archiver', $cours);

        return new CourResource($this->cours->charger($this->cours->archiver($cours), false));
    }

    /** L'administrateur a relu les modifications d'un cours publié. */
    public function marquerRelu(Cours $cours): CourResource
    {
        return new CourResource($this->cours->charger($this->cours->marquerRelu($cours), false));
    }
}
