<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use App\Models\Chapitre;
use App\Services\Apprenant\TelechargementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Téléchargement hors ligne (règles 18 et 19). */
class TelechargementController extends Controller
{
    public function __construct(private TelechargementService $telechargements)
    {
    }

    /** État du hors ligne : accès, crédits restants, téléchargements et leur lisibilité. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->telechargements->etat($request->user())]);
    }

    /** Décompte un crédit (une seule fois par chapitre) puis renvoie le chapitre complet à enregistrer sur l'appareil. */
    public function store(Request $request, Chapitre $chapitre): JsonResponse
    {
        $utilisateur = $request->user();
        $telechargement = $this->telechargements->telecharger($utilisateur, $chapitre);

        $chapitre->load(['blocs' => fn ($q) => $q->orderBy('ordre'), 'blocs.texte', 'blocs.image', 'blocs.code']);

        $etat = $this->telechargements->etat($utilisateur);

        return response()->json(['data' => [
            // Même format que la lecture en ligne : le même écran affiche le chapitre hors ligne.
            'chapitre' => Presenteur::chapitre($chapitre),
            'telechargement' => $this->telechargements->presenter($telechargement, $utilisateur, true),
            'credits_restants' => $etat['credits_restants'],
            'serveur_maintenant' => $etat['serveur_maintenant'],
        ]], 201);
    }

    /** À appeler au moins tous les 7 jours, en ligne : renouvelle la lisibilité si l'abonnement donne accès. */
    public function verifier(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->telechargements->verifier($request->user())]);
    }
}
