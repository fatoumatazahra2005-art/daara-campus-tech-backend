<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Chapitre;
use App\Models\Cours;
use App\Services\Apprenant\ProgressionService;

class ApprentissageController extends Controller
{
    public function __construct(private ProgressionService $progression)
    {
    }

    /** Plan du cours : modules, chapitres, terminés, verrouillés, quiz. */
    public function structure(Request $request, Cours $cours): JsonResponse
    {
        $s = $this->progression->structure($request->user(), $cours);

        return response()->json(['data' => [
            'cours' => ['id' => $s['cours']->id, 'titre' => $s['cours']->titre],
            'inscription' => $s['inscription'] ? Presenteur::inscriptionCours($s['inscription']) : null,
            'acces' => $s['acces'],
            'essai' => $s['essai'],
            'modules' => $s['modules'],
        ]]);
    }

    public function chapitre(Request $request, Chapitre $chapitre): JsonResponse
    {
        $r = $this->progression->ouvrirChapitre($request->user(), $chapitre);

        return response()->json(['data' => Presenteur::chapitre($r['chapitre']) + ['termine' => (bool) $r['progression']->est_termine]]);
    }

    public function terminer(Request $request, Chapitre $chapitre): JsonResponse
    {
        $r = $this->progression->terminerChapitre($request->user(), $chapitre);

        return response()->json(['data' => [
            'inscription' => Presenteur::inscriptionCours($r['inscription']),
            'xp_gagne' => $r['xp_gagne'],
            'xp_total' => (int) $r['apprenant']->xp_total,
            'niveau_xp' => (int) $r['apprenant']->niveau_xp,
            'serie_jours_consecutifs' => (int) $r['apprenant']->serie_jours_consecutifs,
        ]]);
    }
}
