<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Services\Apprenant\PaiementService;

/** Callback de l'opérateur de paiement (route publique, protégée par signature HMAC). */
class PaiementCallbackController extends Controller
{
    public function __construct(private PaiementService $paiements)
    {
    }

    /**
     * Adaptez les noms de champs au format de votre opérateur. Ici : { "reference": "...", "statut": "SUCCES" | "ECHEC" }.
     * La signature est le HMAC-SHA256 du corps brut avec PAIEMENT_WEBHOOK_SECRET, dans l'en-tête X-Signature.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.paiement.secret');
        $attendue = hash_hmac('sha256', $request->getContent(), $secret);

        if ($secret === '' || ! hash_equals($attendue, (string) $request->header('X-Signature'))) {
            return response()->json(['message' => 'Signature invalide.'], 403);
        }

        $data = $request->validate(['reference' => ['required', 'string'], 'statut' => ['required', 'string']]);

        $this->paiements->confirmer($data['reference'], strtoupper($data['statut']) === 'SUCCES');

        return response()->json(['message' => 'OK']);
    }
}
