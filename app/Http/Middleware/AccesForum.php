<?php

namespace App\Http\Middleware;

use App\Services\Apprenant\AccesService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forum réservé aux abonnés : un apprenant doit avoir un abonnement qui donne accès
 * (ESSAI, ACTIF, ou RESILIE dans sa période). Encadreurs et administrateurs passent toujours.
 */
class AccesForum
{
    public function __construct(private AccesService $acces)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur && $utilisateur->aLeRole('APPRENANT')) {
            $abonnement = $this->acces->abonnementOuvert($utilisateur->id);

            if (! $abonnement || ! $this->acces->donneAcces($abonnement)) {
                return response()->json([
                    'message' => 'Le forum est réservé aux abonnés. Abonne-toi pour échanger avec la communauté.',
                    'code' => 'ABONNEMENT_REQUIS',
                ], 403);
            }
        }

        return $next($request);
    }
}
