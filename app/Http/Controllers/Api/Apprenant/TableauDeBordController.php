<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\Apprenant\TableauDeBordService;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBordService $service): JsonResponse
    {
        return response()->json(['data' => $service->pour($request->user())]);
    }
}
