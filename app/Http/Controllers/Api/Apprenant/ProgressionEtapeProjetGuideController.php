<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Models\EtapeProjetGuide;
use App\Services\Apprenant\ProgressionEtapeProjetGuideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressionEtapeProjetGuideController extends Controller
{
    public function __construct(
        private ProgressionEtapeProjetGuideService $service
    ) {
    }

    /**
     * Terminer une étape de projet guidé.
     */
    public function terminer(
        Request $request,
        EtapeProjetGuide $etape
    ): JsonResponse {

        $progression = $this->service->terminer(
            $request->user(),
            $etape
        );

        return response()->json([
            'message' => 'Étape terminée avec succès.',
            'data' => $progression,
        ], 200);
    }
}
