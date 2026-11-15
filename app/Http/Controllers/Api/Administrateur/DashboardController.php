<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    /**
     * Afficher les statistiques générales de la plateforme.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Statistiques du tableau de bord récupérées avec succès.',
            'data' => $this->dashboardService->statistiques(),
        ]);
    }
}
