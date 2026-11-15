<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\RenduProjet;
use App\Services\Apprenant\RenduProjetService;

class RenduProjetController extends Controller
{
    public function __construct(private RenduProjetService $rendus)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->rendus->mesRendus($request->user())->map(fn ($r) => Presenteur::rendu($r))->values()]);
    }

    public function soumettre(Request $request, RenduProjet $rendu): JsonResponse
    {
        abort_unless($rendu->apprenant_id === $request->user()->id, 404);

        $data = $request->validate([
            'lien_github' => ['required', 'url', 'max:255', 'regex:#^https://(www\.)?github\.com/[^/]+/[^/]+#i'],
        ]);

        return response()->json(['data' => Presenteur::rendu($this->rendus->soumettre($request->user(), $rendu, $data['lien_github']))]);
    }
}
