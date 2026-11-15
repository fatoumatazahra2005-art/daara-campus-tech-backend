<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use App\Models\Certificat;
use App\Services\Apprenant\CertificatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificatController extends Controller
{
    public function __construct(private CertificatService $certificats)
    {
    }

    /** Mes certificats (avec rattrapage des certificats mérités pendant l'essai). */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->certificats->mesCertificats($request->user())
            ->map(fn (Certificat $c) => Presenteur::certificat($c) + ['cours_id' => $c->inscriptionCours?->cours_id])
            ->values()]);
    }

    /** Téléchargement du PDF, réservé au titulaire. */
    /** Téléchargement du PDF, réservé au titulaire. */
    public function pdf(Request $request, Certificat $certificat)
    {
        $certificat->loadMissing('inscriptionCours');
        abort_unless((int) $certificat->inscriptionCours?->apprenant_id === (int) $request->user()->id, 404);

        $contenu = $this->certificats->pdf($certificat)->output();
        $nom = 'certificat-'.$certificat->code_verification.'.pdf';

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => strlen($contenu),
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Vérification publique (sans connexion). */
    public function verifier(string $code): JsonResponse
    {
        $c = $this->certificats->verifier($code);

        return response()->json(['data' => [
            'valide' => true,
            'nom_apprenant' => $c->nom_apprenant,
            'titre_cours' => $c->titre_cours,
            'date_obtention' => $c->date_obtention?->toDateString(),
            'code_verification' => $c->code_verification,
        ]]);
    }
}
