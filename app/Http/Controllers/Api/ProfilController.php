<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function __construct(private ProfilService $profils)
    {
    }

    /** Profil de l'utilisateur connecté (admin ou encadreur). */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profils->profil($request->user())]);
    }

    /** Modifier prénom, nom, téléphone, email (+ spécialité pour l'encadreur). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom' => ['sometimes', 'required', 'string', 'max:100'],
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'specialite' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mot_de_passe_actuel' => ['nullable', 'string'],
        ]);

        return response()->json(['data' => $this->profils->modifier($request->user(), $data)]);
    }

    /** Changer le mot de passe ; les autres sessions sont déconnectées. */
    public function motDePasse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mot_de_passe_actuel' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'nouveau_mot_de_passe.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau_mot_de_passe.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ]);

        $this->profils->changerMotDePasse(
            $request->user(),
            $data['mot_de_passe_actuel'],
            $data['nouveau_mot_de_passe'],
            $request->user()->currentAccessToken()?->id,
        );

        return response()->json(['data' => null, 'message' => 'Mot de passe modifié.']);
    }

    public function apprenant(Request $request): JsonResponse
    {
        $apprenant = $request->user()->apprenant()->with('niveau')->firstOrFail();

        return response()->json(['data' => [
            'xp_total' => $apprenant->xp_total,
            'niveau_xp' => $apprenant->niveau_xp,
            'serie_jours_consecutifs' => $apprenant->serie_jours_consecutifs,
            'niveau' => $apprenant->niveau->value,
            'essai_utilise' => $apprenant->essai_utilise,
        ]]);
    }

    public function encadreur(Request $request): JsonResponse
    {
        $encadreur = $request->user()->encadreur()->firstOrFail();

        return response()->json(['data' => [
            'specialite' => $encadreur->specialite,
        ]]);
    }
}
