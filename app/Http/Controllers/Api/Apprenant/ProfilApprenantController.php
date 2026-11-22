<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Rules\TelephoneSenegal;
use App\Services\Apprenant\ProfilApprenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilApprenantController extends Controller
{
    public function __construct(private ProfilApprenantService $profil)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profil->profil($request->user())]);
    }

    /*public function update(Request $request): JsonResponse
    {
        $d = $request->validate([
            'prenom' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'nom' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:191'],
            'telephone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9\s.\-]{7,20}$/'],
            'niveau' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mot_de_passe_actuel' => ['nullable', 'string'],
        ], [
            'telephone.regex' => 'Numéro de téléphone invalide.',
        ]);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data' => $this->profil->modifier($request->user(), $d),
        ]);
    }
    */

    public function update(Request $request): JsonResponse
    {
        $d = $request->validate([
            'prenom' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'nom' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:191'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30', new TelephoneSenegal()],
            'niveau' => ['sometimes', 'nullable', 'string', 'max:30'],
            'mot_de_passe_actuel' => ['nullable', 'string'],
        ]);

        return response()->json([
            'message' => 'Profil mis à jour.',
            'data' => $this->profil->modifier($request->user(), $d),
        ]);
    }

    public function motDePasse(Request $request): JsonResponse
    {
        $d = $request->validate([
            'mot_de_passe_actuel' => ['required', 'string'],
            'nouveau_mot_de_passe' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], [
            'nouveau_mot_de_passe.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'nouveau_mot_de_passe.confirmed' => 'La confirmation ne correspond pas.',
        ]);

        $this->profil->changerMotDePasse(
            $request->user(),
            $d['mot_de_passe_actuel'],
            $d['nouveau_mot_de_passe'],
            $request->user()->currentAccessToken()?->id
        );

        return response()->json(['message' => 'Mot de passe modifié. Vos autres sessions ont été déconnectées.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $d = $request->validate(['mot_de_passe' => ['required', 'string']]);

        $this->profil->supprimerCompte($request->user(), $d['mot_de_passe']);

        return response()->json(['message' => 'Votre compte a été supprimé.']);
    }
}
