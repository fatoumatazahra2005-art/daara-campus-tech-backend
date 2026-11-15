<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Cours;
use App\Services\Apprenant\ProjetGuideApprenantService;

class ProjetGuideApprenantController extends Controller
{
    public function __construct(private ProjetGuideApprenantService $projets)
    {
    }

    /** Projets guidés d'un cours, avec leurs étapes (consigne, code de départ, tests attendus). */
    public function index(Request $request, Cours $cours): JsonResponse
    {
        return response()->json(['data' => $this->projets->duCours($request->user(), $cours)]);
    }
}
