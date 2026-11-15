<?php

namespace App\Services\Apprenant;

use App\Models\Apprenant;
use App\Models\Certificat;
use App\Models\InscriptionCours;
use App\Models\Paiement;
use App\Models\ProgressionChapitre;
use App\Models\RenduProjet;
use App\Models\Utilisateur;

/** Écran d'accueil de l'apprenant : gamification, abonnement, cours en cours, échéances. */
class TableauDeBordService
{
    public function __construct(
        private AccesService $acces,
        private PaiementService $paiements,
    ) {
    }

    public function pour(Utilisateur $utilisateur): array
    {
        $apprenant = Apprenant::findOrFail($utilisateur->id);
        $abonnement = $this->acces->abonnementOuvert($utilisateur->id);

        // Fin d'essai appliquée au passage (ACTIF si payé, sinon EN_ATTENTE_PAIEMENT).
        if ($abonnement) {
            $abonnement = $this->paiements->actualiserFinEssai($abonnement);
        }

        $enCours = InscriptionCours::with(['cours', 'statut'])
            ->where('apprenant_id', $utilisateur->id)
            ->whereHas('statut', fn ($q) => $q->where('value', 'EN_COURS'))
            ->orderByDesc('updated_at')->limit(5)->get()
            ->map(fn (InscriptionCours $i) => [
                'inscription_id' => $i->id,
                'cours' => ['id' => $i->cours_id, 'titre' => $i->cours?->titre],
                'progression' => (float) $i->progression,
                'prochain_chapitre_id' => $this->prochainChapitre($i),     // « reprendre où j'en suis »
            ])->all();

        $prochainePaiement = $abonnement
            ? Paiement::with('statut')
                ->whereHas('periode', fn ($q) => $q->where('abonnement_id', $abonnement->id))
                ->whereHas('statut', fn ($q) => $q->whereIn('value', ['EN_ATTENTE', 'ECHOUE']))
                ->orderBy('date_echeance')->first()
            : null;

        $rendus = RenduProjet::whereHas('statut', fn ($q) => $q->whereIn('value', ['A_FAIRE', 'A_REPRENDRE', 'EN_RETARD']))
            ->where('apprenant_id', $utilisateur->id);

        return [
            'xp_total' => (int) $apprenant->xp_total,
            'niveau_xp' => (int) $apprenant->niveau_xp,
            'xp_pour_prochain_niveau' => ProgressionService::PALIER_XP - ($apprenant->xp_total % ProgressionService::PALIER_XP),
            'palier_xp' => ProgressionService::PALIER_XP,
            'serie_jours_consecutifs' => (int) $apprenant->serie_jours_consecutifs,
            'abonnement' => $abonnement ? [
                'statut' => $abonnement->statut?->value,
                'donne_acces' => $this->acces->donneAcces($abonnement),
                'credits_hors_ligne' => (int) $abonnement->credits_hors_ligne,
                'fin_periode' => $this->acces->periodeEnCours($abonnement)?->date_fin?->toDateString(),
            ] : null,
            'prochaine_echeance' => $prochainePaiement ? [
                'paiement_id' => $prochainePaiement->id,
                'montant' => (int) $prochainePaiement->montant,
                'date_echeance' => $prochainePaiement->date_echeance?->toDateString(),
                'statut' => $prochainePaiement->statut?->value,
            ] : null,
            'cours_en_cours' => $enCours,
            'nb_cours_termines' => InscriptionCours::where('apprenant_id', $utilisateur->id)
                ->whereHas('statut', fn ($q) => $q->where('value', 'TERMINEE'))->count(),
            'nb_certificats' => Certificat::whereHas(
                'inscriptionCours',
                fn ($q) => $q->where('apprenant_id', $utilisateur->id)
            )->count(),
            'rendus_a_faire' => (clone $rendus)->count(),
            'prochaine_date_limite' => (clone $rendus)->min('date_limite'),
        ];
    }

    /** Premier chapitre actif non terminé du cours (null si tout est terminé). */
    private function prochainChapitre(InscriptionCours $inscription): ?int
    {
        $termines = ProgressionChapitre::where('inscription_cours_id', $inscription->id)
            ->where('est_termine', true)->pluck('chapitre_id')->map(fn ($i) => (int) $i)->all();

        return $this->acces->chapitreIdsActifs($inscription->cours_id)
            ->map(fn ($i) => (int) $i)->first(fn ($id) => ! in_array($id, $termines, true));
    }
}
