<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodeList;
use App\Services\Administrateur\CodeListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Administration du référentiel CodeList (rôle ADMIN). */
class CodeListController extends Controller
{
    public function __construct(private CodeListService $codes)
    {
    }

    public function types(): JsonResponse
    {
        return response()->json(['data' => $this->codes->types()]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'recherche' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json(['data' => $this->codes->lister($data['type'] ?? null, $data['recherche'] ?? null)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'value' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z][A-Za-z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:1', 'max:999'],
        ], [
            'value.regex' => 'La valeur commence par une lettre et ne contient que des lettres, chiffres et « _ » (ex. ORANGE_MONEY).',
        ]);

        return response()->json(['data' => $this->codes->ajouter($data)], 201);
    }

    public function update(Request $request, CodeList $codelist): JsonResponse
    {
        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        return response()->json(['data' => $this->codes->modifier($codelist, $data)]);
    }

    public function desactiver(CodeList $codelist): JsonResponse
    {
        return response()->json(['data' => $this->codes->desactiver($codelist)]);
    }

    public function activer(CodeList $codelist): JsonResponse
    {
        return response()->json(['data' => $this->codes->activer($codelist)]);
    }

    public function destroy(CodeList $codelist): JsonResponse
    {
        $this->codes->supprimer($codelist);

        return response()->json(['data' => null]);
    }
}
