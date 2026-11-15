<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Controllers\Controller;
use App\Models\RenduProjet;
use App\Services\Encadreur\CorrectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** « Mes corrections » : rendus affectés à l'encadreur connecté. */
class CorrectionController extends Controller
{
    public function __construct(private CorrectionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'vue'      => ['nullable', Rule::in(CorrectionService::VUES)],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->service->lister($request->user(), $f);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($r) => $this->presenter($r))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'compteurs' => $this->service->compteurs($request->user()),
        ]);
    }

    public function show(Request $request, int $rendu): JsonResponse
    {
        return response()->json(['data' => $this->presenter($this->service->details($request->user(), $rendu))]);
    }

    public function corriger(Request $request, int $rendu): JsonResponse
    {
        $d = $request->validate([
            'note'        => ['required', 'numeric', 'min:0', 'max:20'],
            'commentaire' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'note.required' => 'La note est obligatoire.',
            'note.max' => 'La note ne peut pas dépasser 20.',
            'commentaire.required' => 'Le commentaire est obligatoire : il aide l’apprenant à progresser.',
            'commentaire.min' => 'Le commentaire doit contenir au moins 10 caractères.',
        ]);

        $resultat = $this->service->corriger($request->user(), $rendu, (float) $d['note'], trim($d['commentaire']));

        return response()->json([
            'message' => $resultat->statut?->value === 'CORRIGE'
                ? 'Rendu validé.'
                : 'Rendu à reprendre : l’apprenant a été invité à le corriger.',
            'data' => $this->presenter($resultat),
        ]);
    }

    private function presenter(RenduProjet $r): array
    {
        $apprenant = $r->apprenant?->utilisateur;

        return [
            'id' => $r->id,
            'statut' => $r->statut?->value,
            'date_attribution' => $r->date_attribution?->toDateString(),
            'date_limite' => $r->date_limite?->toDateString(),
            'date_rendu' => $r->date_rendu?->format('Y-m-d H:i:s'),
            // Rendu après la date limite (information pour le correcteur).
            'rendu_en_retard' => $r->date_rendu && $r->date_limite
                && $r->date_rendu->toDateString() > $r->date_limite->toDateString(),
            'lien_github' => $r->lien_github,
            'note' => $r->note !== null ? (float) $r->note : null,
            'commentaire' => $r->commentaire,
            'projet' => $r->projet ? [
                'id' => $r->projet->id,
                'titre' => $r->projet->titre,
                'consigne' => $r->projet->consigne,
                'delai_jours' => (int) $r->projet->delai_jours,
                'note_minimale' => $r->projet->note_minimale !== null ? (float) $r->projet->note_minimale : null,
                'parcours' => $r->projet->parcours ? ['id' => $r->projet->parcours->id, 'titre' => $r->projet->parcours->titre] : null,
            ] : null,
            // Confidentialité : le correcteur voit le nom, pas l'e-mail ni le téléphone.
            'apprenant' => $apprenant ? ['prenom' => $apprenant->prenom, 'nom' => $apprenant->nom] : null,
        ];
    }
}
