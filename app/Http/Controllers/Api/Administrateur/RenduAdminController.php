<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Models\RenduProjet;
use App\Services\RenduAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Rendus de projet : suivi et affectation d'un correcteur (RG35). */
class RenduAdminController extends Controller
{
    public function __construct(private RenduAdminService $service) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'vue'           => ['nullable', Rule::in(RenduAdminService::VUES)],
            'recherche'     => ['nullable', 'string', 'max:100'],
            'parcours_id'   => ['nullable', 'integer'],
            'correcteur_id' => ['nullable', 'integer'],
            'par_page'      => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->service->lister($f);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($r) => $this->presenter($r))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'compteurs' => $this->service->compteurs(),
        ]);
    }

    public function show(int $rendu): JsonResponse
    {
        return response()->json(['data' => $this->presenter($this->service->details($rendu))]);
    }

    public function suggestions(int $rendu): JsonResponse
    {
        return response()->json(['data' => $this->service->suggestions($rendu)]);
    }

    public function affecter(Request $request, int $rendu): JsonResponse
    {
        $d = $request->validate([
            'correcteur_id' => ['required', 'integer'],
        ]);

        $resultat = $this->service->affecter($rendu, (int) $d['correcteur_id']);

        return response()->json([
            'message' => 'Correcteur affecté.',
            'data' => $this->presenter($resultat),
        ]);
    }

    private function presenter(RenduProjet $r): array
    {
        $apprenant = $r->apprenant?->utilisateur;
        $correcteur = $r->correcteur?->utilisateur;
        $statut = $r->statut?->value;

        $enRetard = $statut === 'EN_RETARD'
            || (in_array($statut, ['A_FAIRE', 'A_REPRENDRE'], true) && $r->date_limite && $r->date_limite->isPast());

        return [
            'id' => $r->id,
            'statut' => $statut,
            'en_retard' => $enRetard,
            'date_attribution' => $r->date_attribution?->toDateString(),
            'date_limite' => $r->date_limite?->toDateString(),
            'date_rendu' => $r->date_rendu?->format('Y-m-d H:i:s'),
            'lien_github' => $r->lien_github,
            'note' => $r->note !== null ? (float) $r->note : null,
            'commentaire' => $r->commentaire,
            'projet' => $r->projet ? [
                'id' => $r->projet->id,
                'titre' => $r->projet->titre,
                'note_minimale' => $r->projet->note_minimale !== null ? (float) $r->projet->note_minimale : null,
                'parcours' => $r->projet->parcours
                    ? ['id' => $r->projet->parcours->id, 'titre' => $r->projet->parcours->titre]
                    : null,
            ] : null,
            'apprenant' => $apprenant ? [
                'id' => $apprenant->id,
                'nom' => $apprenant->nom,
                'prenom' => $apprenant->prenom,
                'email' => $apprenant->email,
            ] : null,
            'correcteur' => $correcteur ? [
                'id' => $r->correcteur_id,
                'nom' => trim($correcteur->prenom . ' ' . $correcteur->nom),
                'specialite' => $r->correcteur->specialite,
            ] : null,
        ];
    }
}
