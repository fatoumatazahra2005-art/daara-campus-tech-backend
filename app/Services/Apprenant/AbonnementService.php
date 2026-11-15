<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Abonnement;
use App\Models\Apprenant;
use App\Models\CodeList;
use App\Models\FormuleAbonnement;
use App\Models\Paiement;
use App\Models\PeriodeAbonnement;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Cycle de vie de l'abonnement : souscrire, activer, renouveler, résilier, suspendre, expirer.
 * Les paiements sont gérés par PaiementService (qui appelle activer/reactiver ici).
 */
class AbonnementService
{
    /** Le reliquat hors ligne ne dépasse jamais 2 × quotaHorsLigne. */
    public const PLAFOND_RELIQUAT = 2;

    public function __construct(private AccesService $acces)
    {
    }

    public function formulesActives()
    {
        return FormuleAbonnement::with('periodicite')->where('active', true)->orderBy('prix')->get();
    }

    /**
     * Abonnement.souscrire(formule) : crée l'abonnement et sa première période.
     * ESSAI si la formule propose un essai et que l'essai n'a jamais été utilisé, sinon EN_ATTENTE_PAIEMENT.
     * Le règlement se fait ensuite via PaiementService::payer().
     */
    public function souscrire(Utilisateur $utilisateur, FormuleAbonnement $formule): Abonnement
    {
        if (! $formule->active) {
            throw new RegleMetierException('Cette formule n’est plus proposée.', 422);
        }

        if (blank($utilisateur->telephone)) {
            throw new RegleMetierException('Un numéro de téléphone est obligatoire pour souscrire.', 422);
        }

        return DB::transaction(function () use ($utilisateur, $formule) {
            $apprenant = Apprenant::whereKey($utilisateur->id)->lockForUpdate()->firstOrFail();

            if ($this->acces->abonnementOuvert($apprenant->id)) {
                throw new RegleMetierException('Vous avez déjà un abonnement en cours.', 409);
            }

            $essai = $formule->jours_essai > 0 && ! $apprenant->essai_utilise;
            $debut = today();

            $abonnement = Abonnement::create([
                'statut_id' => CodeList::idOf('StatutAbonnement', $essai ? 'ESSAI' : 'EN_ATTENTE_PAIEMENT'),
                'credits_hors_ligne' => 0,
                'apprenant_id' => $apprenant->id,
                'formule_id' => $formule->id,
            ]);

            $abonnement->periodes()->create([
                'numero' => 1,
                'date_debut' => $debut,
                'date_fin' => $essai
                    ? $debut->copy()->addDays($formule->jours_essai)
                    : $debut->copy()->addMonthsNoOverflow($formule->dureeMois()),
                'prix_paye' => $essai ? 0 : $formule->prix,
                'nb_echeances' => 1,
                'est_essai' => $essai,
                'formule_id' => $formule->id,
            ]);

            if ($essai) {
                $apprenant->update(['essai_utilise' => true]);
            }

            return $abonnement->load('statut', 'formule.periodicite', 'periodes');
        });
    }

    /** Le paiement fractionné n'existe que pour les formules semestrielle et annuelle. */
    public function verifierEcheances(FormuleAbonnement $formule, int $nb): void
    {
        $formule->loadMissing('periodicite');

        if ($nb < 1 || $nb > $formule->nb_echeances_max) {
            throw new RegleMetierException("Cette formule autorise au maximum {$formule->nb_echeances_max} paiement(s).", 422);
        }

        if ($nb > 1 && ! in_array($formule->periodicite?->value, ['SEMESTRIEL', 'ANNUEL'], true)) {
            throw new RegleMetierException('Le paiement fractionné n’est proposé que pour les formules semestrielle et annuelle.', 422);
        }
    }

    /** Crée les lignes d'échéance d'une période : la somme des montants égale prixPaye. */
    public function genererEcheances(PeriodeAbonnement $periode, int $nb, int $methodeId, ?string $numeroPayeur): void
    {
        $periode->loadMissing('formule.periodicite');
        $this->verifierEcheances($periode->formule, $nb);

        $montants = $this->repartir((int) $periode->prix_paye, $nb);
        $enAttente = CodeList::idOf('StatutPaiement', 'EN_ATTENTE');
        $debut = $periode->date_debut->copy()->max(today());

        foreach ($montants as $i => $montant) {
            $periode->paiements()->create([
                'montant' => $montant,
                'date_echeance' => $debut->copy()->addMonthsNoOverflow($i),
                'numero_echeance' => $i + 1,
                'methode_id' => $methodeId,
                'statut_id' => $enAttente,
                'numero_payeur' => $numeroPayeur,
            ]);
        }

        $periode->update(['nb_echeances' => $nb]);
    }

    /** Premier paiement VALIDE : EN_ATTENTE_PAIEMENT vers ACTIF, quota hors ligne crédité. */
    public function activer(Abonnement $abonnement, PeriodeAbonnement $periode): void
    {
        $periode->loadMissing('formule');
        $this->decalerSiEnRetard($periode);

        $abonnement->update([
            'statut_id' => CodeList::idOf('StatutAbonnement', 'ACTIF'),
            'credits_hors_ligne' => (int) $periode->formule->quota_hors_ligne,
        ]);
    }

    /** Échéance impayée réglée : SUSPENDU vers ACTIF. */
    public function reactiver(Abonnement $abonnement): void
    {
        $abonnement->update(['statut_id' => CodeList::idOf('StatutAbonnement', 'ACTIF')]);
    }

    public function suspendre(Abonnement $abonnement): void
    {
        $abonnement->update(['statut_id' => CodeList::idOf('StatutAbonnement', 'SUSPENDU')]);
    }

    /** Abonnement.resilier() : l'accès reste ouvert jusqu'à la fin de la période, aucun renouvellement. */
    public function resilier(Abonnement $abonnement): Abonnement
    {
        if (! $abonnement->aStatut('ACTIF', 'ESSAI')) {
            throw new RegleMetierException('Seul un abonnement actif ou en essai peut être résilié.', 422);
        }

        $abonnement->update([
            'statut_id' => CodeList::idOf('StatutAbonnement', 'RESILIE'),
            'date_resiliation' => today(),
        ]);

        return $abonnement->fresh(['statut', 'formule.periodicite', 'periodes']);
    }

    public function aEcheanceEnRetard(Abonnement $abonnement): bool
    {
        return Paiement::whereHas('periode', fn ($q) => $q->where('abonnement_id', $abonnement->id))
            ->whereHas('statut', fn ($q) => $q->whereIn('value', ['EN_ATTENTE', 'ECHOUE']))
            ->whereDate('date_echeance', '<', today())
            ->exists();
    }

    /** Fin d'essai : crée la période payante et passe en EN_ATTENTE_PAIEMENT (l'apprenant doit payer). */
    public function terminerEssai(Abonnement $abonnement): bool
    {
        return DB::transaction(function () use ($abonnement) {
            $abonnement = Abonnement::whereKey($abonnement->id)
                ->lockForUpdate()
                ->with('statut', 'formule.periodicite')
                ->firstOrFail();

            if (! $abonnement->aStatut('ESSAI')) {
                return false;
            }

            $periodeEssai = $abonnement->periodes()
                ->where('est_essai', true)
                ->orderByDesc('numero')
                ->first();

            if (! $periodeEssai || $periodeEssai->date_fin->gte(today())) {
                return false;
            }

            // Chercher la période payante créée pendant l'essai.
            $periodePayante = $abonnement->periodes()
                ->where('est_essai', false)
                ->orderByDesc('numero')
                ->first();

            // Si elle n'existe pas, la créer maintenant.
            if (! $periodePayante) {
                $formule = $abonnement->formule;
                $debut = $periodeEssai->date_fin->copy()->addDay();

                $periodePayante = $abonnement->periodes()->create([
                    'numero' => $periodeEssai->numero + 1,
                    'date_debut' => $debut,
                    'date_fin' => $debut->copy()->addMonthsNoOverflow($formule->dureeMois()),
                    'prix_paye' => $formule->prix,
                    'nb_echeances' => 1,
                    'est_essai' => false,
                    'formule_id' => $formule->id,
                ]);
            }

            // Vérifier si la première échéance a été confirmée.
            $premiereEcheancePayee = $periodePayante->paiements()
                ->where('numero_echeance', 1)
                ->whereHas('statut', fn ($q) => $q->where('value', 'VALIDE'))
                ->exists();

            if ($premiereEcheancePayee) {
                $this->activer($abonnement, $periodePayante);
            } else {
                $abonnement->update([
                    'statut_id' => CodeList::idOf('StatutAbonnement', 'EN_ATTENTE_PAIEMENT'),
                ]);
            }

            return true;
        });
    }

    /**
     * Abonnement.renouveler() pour un abonnement ACTIF dont la période est terminée : crée la période
     * suivante (sans chevauchement), ajoute le quota hors ligne au reliquat et génère les échéances avec
     * le moyen de paiement du dernier paiement validé. Retourne la 1re échéance à envoyer à l'opérateur.
     */
    public function renouveler(Abonnement $abonnement): ?Paiement
    {
        return DB::transaction(function () use ($abonnement) {
            $abonnement = Abonnement::whereKey($abonnement->id)->lockForUpdate()->with('statut', 'formule.periodicite')->firstOrFail();

            if (! $abonnement->aStatut('ACTIF')) {
                return null;
            }

            $derniere = $abonnement->periodes()->orderByDesc('numero')->first();

            if (! $derniere || $derniere->date_fin->gte(today())) {
                return null;
            }

            $formule = $abonnement->formule;
            $debut = $derniere->date_fin->copy()->addDay();

            $periode = $abonnement->periodes()->create([
                'numero' => $derniere->numero + 1,
                'date_debut' => $debut,
                'date_fin' => $debut->copy()->addMonthsNoOverflow($formule->dureeMois()),
                'prix_paye' => $formule->prix,
                'nb_echeances' => 1,
                'est_essai' => false,
                'formule_id' => $formule->id,
            ]);

            $quota = (int) $formule->quota_hors_ligne;
            $abonnement->update([
                'credits_hors_ligne' => min($abonnement->credits_hors_ligne + $quota, self::PLAFOND_RELIQUAT * $quota),
            ]);

            $dernierPaiement = Paiement::whereHas('periode', fn ($q) => $q->where('abonnement_id', $abonnement->id))
                ->whereHas('statut', fn ($q) => $q->where('value', 'VALIDE'))
                ->latest('id')
                ->first();

            if (! $dernierPaiement) {
                return null;
            }

            $fractionnable = in_array($formule->periodicite?->value, ['SEMESTRIEL', 'ANNUEL'], true);
            $nb = $fractionnable ? max(1, min($derniere->nb_echeances, $formule->nb_echeances_max)) : 1;

            $this->genererEcheances($periode, $nb, $dernierPaiement->methode_id, $dernierPaiement->numero_payeur);

            return $periode->paiements()->orderBy('numero_echeance')->first();
        });
    }

    /**
     * Traitement quotidien : fin d'essai, expiration des résiliés, suspension des impayés, renouvellement.
     *
     * @return array{paiements: list<Paiement>, stats: array<string,int>}
     */
    public function traiterPeriodesEtEcheances(): array
    {
        $stats = ['essais_termines' => 0, 'expires' => 0, 'suspendus' => 0, 'renouvelements' => 0];
        $aInitier = [];

        $parStatut = fn (string $valeur) => Abonnement::whereHas('statut', fn ($q) => $q->where('value', $valeur));

        // chunkById (et non each) : le statut change pendant le parcours, un découpage par offset sauterait des lignes.
        $parStatut('ESSAI')->chunkById(100, function ($lot) use (&$stats) {
            foreach ($lot as $a) {
                $stats['essais_termines'] += $this->terminerEssai($a) ? 1 : 0;
            }
        });

        $parStatut('RESILIE')->chunkById(100, function ($lot) use (&$stats) {
            foreach ($lot as $a) {
                if ($this->periodesTerminees($a)) {
                    $this->expirer($a);
                    $stats['expires']++;
                }
            }
        });

        $parStatut('ACTIF')->chunkById(100, function ($lot) use (&$stats, &$aInitier) {
            foreach ($lot as $a) {
                if ($this->aEcheanceEnRetard($a)) {
                    $this->suspendre($a);
                    $stats['suspendus']++;

                    continue;
                }

                if (! $this->periodesTerminees($a)) {
                    continue;
                }

                // Un compte bloqué ou désactivé ne génère plus de demande de paiement.
                if (Utilisateur::find($a->apprenant_id)?->statutCompte?->value !== 'ACTIF') {
                    $this->expirer($a);
                    $stats['expires']++;

                    continue;
                }

                if ($paiement = $this->renouveler($a)) {
                    $aInitier[] = $paiement;
                    $stats['renouvelements']++;
                }
            }
        });

        return ['paiements' => $aInitier, 'stats' => $stats];
    }

    private function expirer(Abonnement $abonnement): void
    {
        $abonnement->update(['statut_id' => CodeList::idOf('StatutAbonnement', 'EXPIRE')]);
    }

    private function periodesTerminees(Abonnement $abonnement): bool
    {
        $derniere = $abonnement->periodes()->orderByDesc('numero')->first();

        return $derniere !== null && $derniere->date_fin->lt(today());
    }

    /** Si le paiement arrive après dateDebut, la période (et ses échéances impayées) démarre aujourd'hui. */
    private function decalerSiEnRetard(PeriodeAbonnement $periode): void
    {
        $retard = (int) $periode->date_debut->diffInDays(today(), false);

        if ($retard <= 0) {
            return;
        }

        $periode->update([
            'date_debut' => $periode->date_debut->copy()->addDays($retard),
            'date_fin' => $periode->date_fin->copy()->addDays($retard),
        ]);

        $periode->paiements()
            ->whereHas('statut', fn ($q) => $q->where('value', '!=', 'VALIDE'))
            ->get()
            ->each(fn (Paiement $p) => $p->update(['date_echeance' => $p->date_echeance->copy()->addDays($retard)]));
    }

    /** @return list<int> */
    private function repartir(int $prix, int $nb): array
    {
        $base = intdiv($prix, $nb);
        $montants = array_fill(0, $nb, $base);
        $montants[0] += $prix - $base * $nb;

        return $montants;
    }
}
