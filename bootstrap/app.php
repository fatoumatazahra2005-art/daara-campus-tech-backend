<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {

        // Déclaration des alias des middlewares
        $middleware->alias([
            'role' => \App\Http\Middleware\VerifierRole::class,
            'acces.forum' => \App\Http\Middleware\AccesForum::class,
        ]);

        // Redirection des visiteurs non authentifiés
        $middleware->redirectGuestsTo(
            fn (Request $request) =>
            $request->is('api/*') ? null : route('login')
        );

    })

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) =>
        $request->is('api/*') ? null : route('login')
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, \Throwable $exception) =>
                $request->is('api/*') || $request->expectsJson()
        );

        // Gestion centralisée des violations SQL
        $exceptions->render(function (
            QueryException $exception,
            Request $request
        ) {

            if (!$request->is('api/*')) {
                return null;
            }

            $sqlState = $exception->getCode();

            $precedente = $exception->getPrevious();

            if (
                $precedente instanceof \PDOException &&
                isset($precedente->errorInfo[0])
            ) {
                $sqlState = $precedente->errorInfo[0];
            }

            $erreurs = [
                '23503' => [
                    'message' => "Impossible de supprimer cet élément car il est encore utilisé par d'autres données.",
                    'statut' => 409,
                ],

                '23505' => [
                    'message' => "Cette valeur existe déjà. Veuillez en choisir une autre.",
                    'statut' => 409,
                ],

                '23502' => [
                    'message' => "Un champ obligatoire est manquant.",
                    'statut' => 422,
                ],

                '23514' => [
                    'message' => "Les données fournies ne respectent pas les règles autorisées.",
                    'statut' => 422,
                ],
            ];

            if (!isset($erreurs[$sqlState])) {
                return null;
            }

            $erreur = $erreurs[$sqlState];

            return response()->json([
                'message' => $erreur['message'],
            ], $erreur['statut']);

        });

    })
    ->create();
