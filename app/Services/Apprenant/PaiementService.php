<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Abonnement;
use App\Models\CodeList;
use App\Models\Paiement;
use App\Models\PeriodeAbonnement;
use App\Models\Utilisateur;
use App\Services\Paiement\PasserellePaiement;
use Illuminate\Support\Facades\DB;

/**
 * Paiement.payer(), confirmation par l'opérateur (callback ou simulateur) et fin d'essai.
 *
 * Paiement pendant l'essai : la période payante est créée tout de suite (elle commence le lendemain
 * de la fin de l'essai) ; le paiement VALIDE de sa première échéance passe l'abonnement ACTIF :
 * tout est débloqué et les jours d'essai restants sont offerts.
 */
class PaiementService
{
    public const MOBILE_MONEY = ['WAVE', 'ORANGE_MONEY', 'FREE_MONEY'];

    /** Préfixes acceptés par opérateur : Orange 77 et 78, Free 76, Wave tous les mobiles. */
    public const PREFIXES_OPERATEUR = [
        'WAVE' => ['70', '75', '76', '77', '78'],
        'ORANGE_MONEY' => ['77', '78'],
        'FREE_MONEY' => ['76'],
    ];

    public function __construct(
        private AccesService $acces,
        private AbonnementService $abonnements,
        private PasserellePaiement $passerelle,
    ) {
    }

    /**
     * Règle la prochaine échéance de la période payante (génère les échéances si besoin).
     * Sert au premier paiement (EN_ATTENTE_PAIEMENT), au paiement pendant l'essai (ESSAI),
     * aux échéances suivantes (ACTIF, RESILIE), à la régularisation d'un impayé (SUSPENDU)
     * et à la relance d'un paiement ECHOUE.
     */
    public function payer(Utilisateur $utilisateur, string $methode, ?string $numeroPayeur, int $nbEcheances = 1): Paiement
    {
        if (blank($utilisateur->telephone)) {
            throw new RegleMetierException('Un numéro de téléphone est obligatoire pour payer.', 422);
        }

        $methodeId = $this->methodeId($methode);
        $numeroPayeur = $this->verifierNumeroPayeur($methode, $numeroPayeur);

        $paiementId = DB::transaction(function () use ($utilisateur, $methodeId, $numeroPayeur, $nbEcheances) {
            $ouvert = $this->acces->abonnementOuvert($utilisateur->id)
                ?? throw new RegleMetierException('Aucun abonnement en cours.', 404);

            $abonnement = Abonnement::whereKey($ouvert->id)
                ->lockForUpdate()
                ->with('statut', 'formule.periodicite')
                ->firstOrFail();

            if (! $abonnement->aStatut('EN_ATTENTE_PAIEMENT', 'ESSAI', 'SUSPENDU', 'ACTIF', 'RESILIE')) {
                throw new RegleMetierException('Aucun paiement à effectuer pour cet abonnement.', 422);
            }

            if ($abonnement->aStatut('ESSAI')) {
                // Paiement pendant l'essai : la période payante suit l'essai (créée si besoin).
                $periodeEssai = $abonnement->periodes()
                    ->where('est_essai', true)
                    ->orderByDesc('numero')
                    ->first()
                    ?? throw new RegleMetierException('Période d’essai introuvable.', 422);

                $periode = $abonnement->periodes()
                    ->where('est_essai', false)
                    ->orderByDesc('numero')
                    ->first()
                    ?? $this->creerPeriodeApresEssai($abonnement, $periodeEssai);
            } else {
                $periode = $abonnement->periodes()
                    ->where('est_essai', false)
                    ->orderByDesc('numero')
                    ->first()
                    ?? throw new RegleMetierException('Aucune période payante à régler.', 422);
            }

            // Générer les échéances seulement si aucune n'existe.
            if (! $periode->paiements()->exists()) {
                $this->abonnements->genererEcheances($periode, $nbEcheances, $methodeId, $numeroPayeur);
            }

            $prochain = $periode->paiements()
                ->whereHas('statut', fn ($q) => $q->whereIn('value', ['EN_ATTENTE', 'ECHOUE']))
                ->orderBy('numero_echeance')
                ->first()
                ?? throw new RegleMetierException('Toutes les échéances sont déjà réglées.', 409);

            $prochain->update([
                'methode_id' => $methodeId,
                'numero_payeur' => $numeroPayeur,
            ]);

            return $prochain->id;
        });

        // L'appel à l'opérateur se fait hors transaction.
        return $this->initier(Paiement::findOrFail($paiementId));
    }

    /** Envoie la demande de paiement à l'opérateur (EN_ATTENTE ou ECHOUE, donc relançable). */
    public function initier(Paiement $paiement): Paiement
    {
        $paiement->loadMissing('statut', 'methode');

        if (! $paiement->aStatut('EN_ATTENTE', 'ECHOUE')) {
            throw new RegleMetierException('Ce paiement ne peut plus être initié.', 422);
        }

        if ($paiement->aStatut('EN_ATTENTE') && $paiement->reference && $paiement->updated_at->gt(now()->subMinutes(5))) {
            throw new RegleMetierException('Une demande de paiement est déjà en cours : validez-la sur votre téléphone.', 409);
        }

        if (in_array($paiement->methode?->value, self::MOBILE_MONEY, true) && blank($paiement->numero_payeur)) {
            throw new RegleMetierException('Le numéro payeur est obligatoire pour ce moyen de paiement.', 422);
        }

        $reference = $this->passerelle->demander($paiement);

        $paiement->update([
            'reference' => $reference,
            'statut_id' => CodeList::idOf('StatutPaiement', 'EN_ATTENTE'),
            'date_notification' => null,
        ]);

        return $paiement->fresh(['statut', 'methode']);
    }

    /**
     * Confirmation de l'opérateur (callback ou simulateur) : idempotente.
     * Un paiement VALIDE active l'abonnement (1re échéance, y compris pendant l'essai) ou le réactive (impayé réglé).
     */
    public function confirmer(string $reference, bool $succes): Paiement
    {
        return DB::transaction(function () use ($reference, $succes) {
            $paiement = Paiement::where('reference', $reference)->lockForUpdate()->firstOrFail();

            if ($paiement->aStatut('VALIDE')) {
                return $paiement;
            }

            $paiement->update([
                'statut_id' => CodeList::idOf('StatutPaiement', $succes ? 'VALIDE' : 'ECHOUE'),
                'date_paiement' => $succes ? now() : null,
                'date_notification' => now(),
            ]);

            if ($succes) {
                $this->apresPaiementValide($paiement->fresh(['periode.abonnement.statut', 'periode.formule']));
            }

            return $paiement->fresh(['statut', 'methode']);
        });
    }

    /**
     * Fin d'essai à la consultation (en plus du traitement de la nuit) :
     * ACTIF si la période payante est déjà payée, sinon EN_ATTENTE_PAIEMENT. Sans effet si l'essai n'est pas fini.
     */
    public function actualiserFinEssai(Abonnement $abonnement): Abonnement
    {
        $this->abonnements->terminerEssai($abonnement);

        return $abonnement->fresh(['statut', 'formule.periodicite']);
    }

    public function mesPaiements(Utilisateur $utilisateur)
    {
        return Paiement::with(['statut', 'methode', 'periode'])
            ->whereHas('periode.abonnement', fn ($q) => $q->where('apprenant_id', $utilisateur->id))
            ->orderByDesc('date_echeance')
            ->orderByDesc('id')
            ->get();
    }

    // =====================================================================
    // Outils internes
    // =====================================================================

    private function apresPaiementValide(Paiement $paiement): void
    {
        $periode = $paiement->periode;
        $abonnement = $periode->abonnement;
        $premiere = (int) $paiement->numero_echeance === 1;

        // Premier paiement : EN_ATTENTE_PAIEMENT → ACTIF (règle 4), ou pendant l'essai → ACTIF tout de suite
        // (tout est débloqué, les jours d'essai restants sont offerts, la période payée suit l'essai).
        if ($premiere && $abonnement->aStatut('EN_ATTENTE_PAIEMENT', 'ESSAI')) {
            $this->abonnements->activer($abonnement, $periode);

            return;
        }

        // Règle 8 : impayé réglé, SUSPENDU → ACTIF (ou RESILIE s'il avait résilié).
        if ($abonnement->aStatut('SUSPENDU')) {
            $this->abonnements->reactiver($abonnement);
        }
    }

    /** Période payante qui suit l'essai : commence le lendemain de sa fin, dure la périodicité de la formule. */
    private function creerPeriodeApresEssai(Abonnement $abonnement, PeriodeAbonnement $periodeEssai): PeriodeAbonnement
    {
        $formule = $abonnement->formule;
        $debut = $periodeEssai->date_fin->copy()->addDay();

        return $abonnement->periodes()->create([
            'numero' => (int) $abonnement->periodes()->max('numero') + 1,
            'date_debut' => $debut,
            'date_fin' => $debut->copy()->addMonthsNoOverflow($formule->dureeMois()),
            'prix_paye' => $formule->prix,
            'nb_echeances' => 1,
            'est_essai' => false,
            'formule_id' => $formule->id,
        ]);
    }

    private function methodeId(string $methode): int
    {
        $id = CodeList::where('type', 'MethodePaiement')->where('value', $methode)->where('active', true)->value('id');

        return $id ?? throw new RegleMetierException('Moyen de paiement inconnu ou indisponible.', 422);
    }

    /**
     * Règle 12 : numéro obligatoire en mobile money, au format sénégalais, et compatible avec l'opérateur
     * (Orange Money : 77 ou 78 ; Free Money : 76 ; Wave : 70, 75, 76, 77 ou 78).
     * Retourne le numéro au format enregistré (+221771234567), ou null pour une carte.
     */
    private function verifierNumeroPayeur(string $methode, ?string $numeroPayeur): ?string
    {
        $operateur = $this->operateur($methode);

        if ($operateur === null) {
            return null; // Carte bancaire : pas de numéro payeur.
        }

        if (blank($numeroPayeur)) {
            throw new RegleMetierException('Le numéro payeur est obligatoire pour ce moyen de paiement.', 422);
        }

        $n = preg_replace('/[\s.\-()]/', '', $numeroPayeur);
        $n = preg_replace('/^(\+221|00221|221)/', '', $n);

        if (! preg_match('/^7[05678]\d{7}$/', $n)) {
            throw new RegleMetierException('Numéro invalide : 9 chiffres commençant par 70, 75, 76, 77 ou 78.', 422);
        }

        if (! in_array(substr($n, 0, 2), self::PREFIXES_OPERATEUR[$operateur], true)) {
            throw new RegleMetierException(match ($operateur) {
                'ORANGE_MONEY' => 'Orange Money n’accepte que les numéros Orange (77 ou 78).',
                'FREE_MONEY' => 'Free Money n’accepte que les numéros Free (76).',
                default => 'Ce numéro ne correspond pas à l’opérateur choisi.',
            }, 422);
        }

        return '+221'.$n;
    }

    /** WAVE, ORANGE_MONEY, FREE_MONEY, ou null (carte) — accepte aussi « ORANGE », « Free Money »… */
    private function operateur(string $methode): ?string
    {
        $m = strtoupper($methode);

        return match (true) {
            str_contains($m, 'ORANGE') => 'ORANGE_MONEY',
            str_contains($m, 'FREE') => 'FREE_MONEY',
            str_contains($m, 'WAVE') => 'WAVE',
            default => null,
        };
    }
}
