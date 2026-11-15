<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierRole
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {

        // Récupérer l'utilisateur connecté
        $utilisateur = $request->user();

        // Vérifier si l'utilisateur possède le rôle demandé
        if (! $utilisateur || ! $utilisateur->aLeRole($role)) {

            return response()->json([
                'message' => 'Accès interdit.'
            ], 403);
        }

        // Autoriser l'accès à la route
        return $next($request);
    }
}
