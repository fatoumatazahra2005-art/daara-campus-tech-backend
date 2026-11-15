<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Cours;
use App\Services\Apprenant\CatalogueApprenantService;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogueApprenantController extends Controller
{
    public function __construct(private CatalogueApprenantService $catalogue)
    {
    }

    /** Cours publiés, filtrables : technologie_id, niveau (DEBUTANT...), certifiant, recherche. */
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'technologie_id' => ['nullable', 'integer'],
            'niveau' => ['nullable', 'string', 'max:30'],
            'certifiant' => ['nullable', 'boolean'],
            'recherche' => ['nullable', 'string', 'max:100'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->catalogue->lister($request->user(), $f, (int) ($f['par_page'] ?? 12));

        return response()->json(JsonResource::collection($page)->response()->getData(true));
    }

    public function show(Request $request, Cours $cours): JsonResponse
    {
        return response()->json(['data' => $this->catalogue->detail($request->user(), $cours)]);
    }
}
