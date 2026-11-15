<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UtilisateurResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth
    ) {
    }

    /**
     * Inscription d'un apprenant.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $resultat = $this->auth->inscrire(
            $request->validated()
        );

        return response()->json([
            'message' => 'Inscription réussie.',
            'token' => $resultat['token'],
            'utilisateur' => new UtilisateurResource(
                $resultat['utilisateur']
            ),
        ], 201);
    }

    /**
     * Connexion d'un utilisateur.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $resultat = $this->auth->connecter(
            $request->input('email'),
            $request->input('password'),
            $request->input('appareil', 'mobile')
        );

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $resultat['token'],
            'utilisateur' => new UtilisateurResource(
                $resultat['utilisateur']
            ),
        ], 200);
    }

    /**
     * Déconnexion de l'utilisateur connecté.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->deconnecter(
            $request->user()
        );

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ], 200);
    }

    /**
     * Récupérer les informations de l'utilisateur connecté.
     */
    public function me(Request $request): UtilisateurResource
    {
        return new UtilisateurResource(
            $request->user()->load([
                'role',
                'statutCompte',
                'apprenant.niveau',
                'encadreur',
                'administrateur',
            ])
        );
    }

    /** Connexion de l'interface web : administrateurs et encadreurs uniquement. */
    public function connexionWeb(Request $request, AuthService $authService): JsonResponse
    {
        $donnees = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $resultat = $authService->connecter(
            $donnees['email'],
            $donnees['password'],
            'web',
            ['ADMIN', 'ENCADREUR'],
        );

        return response()->json($resultat);
    }
}
