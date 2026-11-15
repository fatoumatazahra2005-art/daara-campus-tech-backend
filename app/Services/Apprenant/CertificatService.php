<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Certificat;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\Utilisateur;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class CertificatService
{
    public function __construct(private AccesService $acces)
    {
    }

    /**
     * Délivré une seule fois, quand une inscription à un cours certifiant est TERMINEE avec un
     * abonnement ACTIF ou RESILIE donnant accès (jamais pendant l'essai). Nom et titre sont figés.
     */
    public function delivrerSiEligible(InscriptionCours $inscription): ?Certificat
    {
        $inscription->loadMissing('statut');

        if (! $inscription->aStatut('TERMINEE')) {
            return null;
        }

        $cours = Cours::find($inscription->cours_id);

        if (! $cours || ! $cours->est_certifiant) {
            return null;
        }

        if (Certificat::where('inscription_cours_id', $inscription->id)->exists()) {
            return null;
        }

        $abonnement = $this->acces->abonnementOuvert($inscription->apprenant_id);

        if (! $abonnement || ! $abonnement->aStatut('ACTIF', 'RESILIE') || ! $this->acces->donneAcces($abonnement)) {
            return null;
        }

        $apprenant = Utilisateur::findOrFail($inscription->apprenant_id);

        return Certificat::create([
            'date_obtention' => today(),
            'code_verification' => $this->codeUnique(),
            'nom_apprenant' => trim($apprenant->prenom.' '.$apprenant->nom),
            'titre_cours' => $cours->titre,
            'url_pdf' => null,
            'inscription_cours_id' => $inscription->id,
        ]);
    }

    /**
     * Rattrapage : cours certifiants terminés pendant l'essai (ou avant un paiement),
     * délivrés dès que l'abonnement est ACTIF.
     */
    public function rattraper(Utilisateur $utilisateur): void
    {
        InscriptionCours::with('statut')
            ->where('apprenant_id', $utilisateur->id)
            ->whereHas('statut', fn ($q) => $q->where('value', 'TERMINEE'))
            ->whereDoesntHave('certificat')
            ->get()
            ->each(fn (InscriptionCours $i) => $this->delivrerSiEligible($i));
    }

    /** Toujours consultables, avec ou sans abonnement. */
    public function mesCertificats(Utilisateur $utilisateur)
    {
        $this->rattraper($utilisateur);

        return Certificat::with('inscriptionCours')
            ->whereHas('inscriptionCours', fn ($q) => $q->where('apprenant_id', $utilisateur->id))
            ->orderByDesc('date_obtention')
            ->orderByDesc('id')
            ->get();
    }

    /** Vérification publique d'authenticité. */
    public function verifier(string $code): Certificat
    {
        return Certificat::where('code_verification', strtoupper(trim($code)))->first()
            ?? throw new RegleMetierException('Certificat introuvable.', 404);
    }

    /** PDF généré à la demande (A4 paysage). */
    /** PDF généré à la demande (A4 paysage), polices réduites aux caractères utilisés. */
    public function pdf(Certificat $certificat)
    {
        return Pdf::setOption([
            'isFontSubsettingEnabled' => true, // n'embarque que les glyphes utilisés (~ 30 Ko au lieu de ~ 880 Ko)
            'isRemoteEnabled' => false,
        ])->loadView('certificats.pdf', [
            'c' => $certificat,
            'urlVerification' => url('/verifier/'.$certificat->code_verification),
        ])->setPaper('a4', 'landscape');
    }

    private function codeUnique(): string
    {
        do {
            $code = 'CERT-'.Str::upper(Str::random(12));
        } while (Certificat::where('code_verification', $code)->exists());

        return $code;
    }
}
