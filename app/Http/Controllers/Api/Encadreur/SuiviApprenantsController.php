<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Controllers\Controller;
use App\Models\InscriptionCours;
use App\Services\Encadreur\SuiviApprenantsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Suivi des apprenants inscrits aux cours de l'encadreur connecté. */
class SuiviApprenantsController extends Controller
{
    public function __construct(private SuiviApprenantsService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'cours_id' => ['nullable', 'integer'],
            'statut' => ['nullable', Rule::in(['EN_COURS', 'TERMINEE', 'ABANDONNEE'])],
            'recherche' => ['nullable', 'string', 'max:100'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->service->lister($request->user(), $f) + [
                'resume' => $this->service->resume($request->user()),
            ]);
    }

    public function show(Request $request, InscriptionCours $inscription): JsonResponse
    {
        return response()->json(['data' => $this->service->detail($request->user(), $inscription)]);
    }
}
