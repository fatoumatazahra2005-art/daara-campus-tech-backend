<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Abonnement;
use App\Models\Chapitre;
use App\Models\Telechargement;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

/**
 * Contenu enregistré hors connexion (règles 18 et 19 du dictionnaire).
 * - Règle 18 : télécharger un chapitre décompte 1 crédit, une seule fois par chapitre et par abonnement ;
 *   aucun crédit pendant l'essai (les crédits sont attribués à l'activation).
 * - Règle 19 : l'application revérifie l'accès en ligne au moins tous les 7 jours ; sans vérification
 *   réussie depuis 7 jours, ou si l'abonnement ne donne plus accès, le contenu téléchargé devient illisible.
 * - Un chapitre retiré du cours (inactif) ou un cours qui n'est plus lisible devient illisible lui aussi.
 */
class TelechargementService
{
    public const VALIDITE_JOURS = 7;

    public function __construct(private AccesService $acces, private ProgressionService $progression)
    {
    }

    /** Décompte 1 crédit, une seule fois par chapitre et par abonnement. Retourne le téléchargement. */
    public function telecharger(Utilisateur $utilisateur, Chapitre $chapitre): Telechargement
    {
        return DB::transaction(function () use ($utilisateur, $chapitre) {
            // Cours lisible, abonnement qui donne accès aujourd'hui, inscription, limite de l'essai.
            $ctx = $this->progression->contexte($utilisateur, $chapitre);

            $abonnement = Abonnement::whereKey($ctx['abonnement']->id)->lockForUpdate()->with('formule')->firstOrFail();

            if ((int) $abonnement->formule->quota_hors_ligne <= 0) {
                throw new RegleMetierException('Votre formule n’inclut pas le mode hors ligne.', 422);
            }

            $existant = Telechargement::where('abonnement_id', $abonnement->id)->where('chapitre_id', $chapitre->id)->first();

            // Retéléchargement (autre appareil, contenu mis à jour) : gratuit.
            if ($existant) {
                $existant->update(['date_telechargement' => now(), 'date_derniere_verification' => now()]);

                return $existant->load('chapitre.module.cours');
            }

            if ((int) $abonnement->credits_hors_ligne < 1) {
                throw new RegleMetierException(
                    $ctx['abonnement']->statut?->value === 'ESSAI'
                        ? 'Le téléchargement hors ligne n’est pas inclus dans l’essai gratuit.'
                        : 'Vous n’avez plus de crédits de téléchargement pour cette période.',
                    422
                );
            }

            $abonnement->decrement('credits_hors_ligne');

            return Telechargement::create([
                'date_telechargement' => now(),
                'date_derniere_verification' => now(),
                'chapitre_id' => $chapitre->id,
                'abonnement_id' => $abonnement->id,
            ])->load('chapitre.module.cours');
        });
    }

    /**
     * Revérification en ligne : si l'abonnement donne toujours accès, la date de dernière vérification
     * est renouvelée pour tous ses téléchargements ; sinon le contenu devient illisible.
     */
    public function verifier(Utilisateur $utilisateur): array
    {
        $abonnement = $this->acces->abonnementOuvert($utilisateur->id);

        if ($abonnement && $this->acces->donneAcces($abonnement)) {
            Telechargement::where('abonnement_id', $abonnement->id)->update(['date_derniere_verification' => now()]);
        }

        return $this->etat($utilisateur);
    }

    /**
     * État du hors ligne de l'apprenant : accès, crédits, et la liste de ses téléchargements avec leur lisibilité.
     * Après une expiration, la liste reste celle du dernier abonnement (tout y est illisible) : l'application
     * sait ainsi quels chapitres bloquer, sans les faire disparaître.
     *
     * @return array<string,mixed>
     */
    public function etat(Utilisateur $utilisateur): array
    {
        $ouvert = $this->acces->abonnementOuvert($utilisateur->id);
        $acces = $ouvert !== null && $this->acces->donneAcces($ouvert);

        $abonnement = $ouvert ?? Abonnement::where('apprenant_id', $utilisateur->id)->latest('id')->first();
        $abonnement?->loadMissing('formule');

        return [
            'acces' => $acces,
            'credits_restants' => $acces ? (int) $abonnement->credits_hors_ligne : 0,
            'quota_par_periode' => (int) ($abonnement?->formule?->quota_hors_ligne ?? 0),
            'validite_jours' => self::VALIDITE_JOURS,
            // Heure du serveur : référence fiable pour le calcul des 7 jours (l'heure du téléphone peut être faussée).
            'serveur_maintenant' => now()->toIso8601String(),
            'telechargements' => $abonnement ? $this->liste($utilisateur, $abonnement, $acces) : [],
        ];
    }

    /** Un téléchargement mis en forme (utilisé aussi par la réponse du téléchargement). */
    public function presenter(Telechargement $t, Utilisateur $utilisateur, bool $acces): array
    {
        $t->loadMissing('chapitre.module.cours.statut');

        $limite = $t->date_derniere_verification->copy()->addDays(self::VALIDITE_JOURS);
        $chapitre = $t->chapitre;
        $cours = $chapitre?->module?->cours;

        $disponible = $chapitre !== null
            && $chapitre->actif
            && $chapitre->module?->actif
            && $cours !== null
            && $this->coursLisible($utilisateur->id, $cours);

        return [
            'id' => $t->id,
            'chapitre_id' => $t->chapitre_id,
            'titre' => $chapitre?->titre,
            'cours' => $cours ? ['id' => $cours->id, 'titre' => $cours->titre] : null,
            'date_telechargement' => $t->date_telechargement->toIso8601String(),
            'date_derniere_verification' => $t->date_derniere_verification->toIso8601String(),
            'lisible_jusqu_au' => $limite->toIso8601String(),
            'lisible' => $acces && $disponible && now()->lte($limite),
            // Pourquoi il n'est plus lisible (pour un message clair dans l'application).
            'raison' => match (true) {
                ! $disponible => 'CONTENU_RETIRE',
                ! $acces => 'ABONNEMENT',
                now()->gt($limite) => 'VERIFICATION_REQUISE',
                default => null,
            },
        ];
    }

    /** @return list<array<string,mixed>> */
    private function liste(Utilisateur $utilisateur, Abonnement $abonnement, bool $acces): array
    {
        return Telechargement::with('chapitre.module.cours.statut')
            ->where('abonnement_id', $abonnement->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Telechargement $t) => $this->presenter($t, $utilisateur, $acces))
            ->all();
    }

    private function coursLisible(int $apprenantId, $cours): bool
    {
        try {
            $this->acces->exigerCoursAccessible($apprenantId, $cours);

            return true;
        } catch (RegleMetierException) {
            return false;
        }
    }
}
