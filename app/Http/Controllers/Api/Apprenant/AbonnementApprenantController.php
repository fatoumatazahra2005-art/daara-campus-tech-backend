<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use App\Models\FormuleAbonnement;
use App\Models\Paiement;
use App\Services\Apprenant\AbonnementService;
use App\Services\Apprenant\AccesService;
use App\Services\Apprenant\PaiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AbonnementApprenantController extends Controller
{
    public function __construct(
        private AbonnementService $abonnements,
        private PaiementService $paiements,
        private AccesService $acces,
    ) {
    }

    public function formules(): JsonResponse
    {
        return response()->json(['data' => $this->abonnements->formulesActives()->map(fn ($f) => Presenteur::formule($f))->values()]);
    }

    /** Abonnement en cours (data = null si l'apprenant n'en a pas). La fin d'essai est appliquée au passage. */
    public function courant(Request $request): JsonResponse
    {
        $ab = $this->acces->abonnementOuvert($request->user()->id);

        if ($ab) {
            $ab = $this->paiements->actualiserFinEssai($ab);
        }

        return response()->json(['data' => $ab ? Presenteur::abonnement($ab) : null]);
    }

    public function souscrire(Request $request): JsonResponse
    {
        $data = $request->validate(['formule_id' => ['required', 'integer', Rule::exists(FormuleAbonnement::class, 'id')]]);

        $ab = $this->abonnements->souscrire($request->user(), FormuleAbonnement::findOrFail($data['formule_id']));

        return response()->json(['data' => Presenteur::abonnement($ab)], 201);
    }

    public function payer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'methode' => ['required', 'string', 'max:50'],
            'numero_payeur' => ['nullable', 'string', 'max:20'],
            'nb_echeances' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);

        $paiement = $this->paiements->payer($request->user(), $data['methode'], $data['numero_payeur'] ?? null, $data['nb_echeances'] ?? 1);

        return response()->json(['data' => Presenteur::paiement($paiement)], 202);
    }

    public function resilier(Request $request): JsonResponse
    {
        $ab = $this->acces->abonnementOuvert($request->user()->id)
            ?? throw new RegleMetierException('Aucun abonnement en cours.', 404);

        return response()->json(['data' => Presenteur::abonnement($this->abonnements->resilier($ab))]);
    }

    public function paiements(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->paiements->mesPaiements($request->user())->map(fn ($p) => Presenteur::paiement($p))->values()]);
    }

    /** Relance un paiement échoué ou impayé. */
    public function relancer(Request $request, int $paiement): JsonResponse
    {
        $p = Paiement::whereKey($paiement)
            ->whereHas('periode.abonnement', fn ($q) => $q->where('apprenant_id', $request->user()->id))
            ->firstOrFail();

        return response()->json(['data' => Presenteur::paiement($this->paiements->initier($p))], 202);
    }
}
