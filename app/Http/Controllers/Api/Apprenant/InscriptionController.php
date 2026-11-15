<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\InscriptionParcours;
use App\Models\Parcours;
use App\Services\Apprenant\InscriptionService;
use App\Services\Apprenant\ParcoursSuiviService;

class InscriptionController extends Controller
{
    public function __construct(private InscriptionService $inscriptions, private ParcoursSuiviService $parcours)
    {
    }

    /** Apprenant.consulterProgression() */
    public function index(Request $request): JsonResponse
    {
        $p = $this->inscriptions->progression($request->user());

        return response()->json(['data' => [
            'cours' => $p['cours']->map(fn ($i) => Presenteur::inscriptionCours($i))->values(),
            'parcours' => $p['parcours']->map(fn ($i) => Presenteur::inscriptionParcours($i))->values(),
        ]]);
    }

    public function sInscrire(Request $request, Cours $cours): JsonResponse
    {
        return response()->json(['data' => Presenteur::inscriptionCours($this->inscriptions->sInscrire($request->user(), $cours))], 201);
    }

    public function abandonner(Request $request, InscriptionCours $inscription): JsonResponse
    {
        $this->posseder($request, $inscription->apprenant_id);

        return response()->json(['data' => Presenteur::inscriptionCours($this->inscriptions->abandonner($inscription))]);
    }

    public function reprendre(Request $request, InscriptionCours $inscription): JsonResponse
    {
        $this->posseder($request, $inscription->apprenant_id);

        return response()->json(['data' => Presenteur::inscriptionCours($this->inscriptions->reprendre($inscription))]);
    }

   /* public function catalogueParcours(): JsonResponse
    {
        return response()->json(['data' => $this->parcours->catalogue()->map(fn ($p) => Presenteur::parcours($p))->values()]);
    }
   */

    /**
     * Catalogue des parcours : les parcours publiés, plus les parcours archivés auxquels l'apprenant est inscrit
     * (il peut ouvrir leur fiche et les terminer). « archive » permet d'afficher un badge.
     */
    public function catalogueParcours(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->parcours->catalogue($request->user()->id)
            ->map(fn ($p) => Presenteur::parcours($p) + [
                    'statut' => $p->statut?->value,
                    'archive' => $p->statut?->value === 'ARCHIVE',
                ])
            ->values()]);
    }

    public function parcoursInscription(Request $request, Parcours $parcours): JsonResponse
    {
        return response()->json(['data' => Presenteur::inscriptionParcours($this->parcours->sInscrire($request->user(), $parcours))], 201);
    }

    public function abandonnerParcours(Request $request, InscriptionParcours $inscriptionParcours): JsonResponse
    {
        $this->posseder($request, $inscriptionParcours->apprenant_id);

        return response()->json(['data' => Presenteur::inscriptionParcours($this->parcours->abandonner($inscriptionParcours))]);
    }

    private function posseder(Request $request, int $apprenantId): void
    {
        abort_unless($apprenantId === $request->user()->id, 404);
    }
}
