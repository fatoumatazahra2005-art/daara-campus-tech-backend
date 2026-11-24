<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\RenduProjet;
use App\Models\Utilisateur;

/**
 * Gestion des rendus des projets de parcours côté apprenant.
 *
 * Règles métier :
 * - Un projet attribué reste accessible à l'apprenant même s'il est désactivé.
 * - La désactivation d'un projet ne supprime pas son rendu.
 * - Les rendus EN_ATTENTE ne sont pas encore soumis à un délai.
 * - Les rendus A_FAIRE, A_REPRENDRE et EN_RETARD peuvent être soumis.
 * - Les rendus déjà soumis ou corrigés restent conservés.
 * - Un projet ajouté après l'inscription n'est pas attribué rétroactivement.
 */
class RenduProjetService
{
    /**
     * Statuts d'un rendu qui peut être soumis par l'apprenant.
     */
    private const A_FAIRE = [
        'A_FAIRE',
        'A_REPRENDRE',
        'EN_RETARD',
    ];

    public function __construct(private AccesService $acces)
    {
    }

    /**
     * Retourne tous les rendus attribués à l'apprenant.
     *
     * Les rendus des projets désactivés restent visibles.
     * Les rendus EN_ATTENTE sont également visibles.
     */

    public function mesRendus(Utilisateur $utilisateur)
    {
        // Mettre à jour les rendus dont le délai est dépassé.
        $this->actualiserRetards($utilisateur->id);

        return RenduProjet::with(['statut', 'projet'])
            ->where('apprenant_id', $utilisateur->id)
            ->whereHas('projet.parcours.inscriptions', function ($q) use ($utilisateur) {
                $q->where('apprenant_id', $utilisateur->id)
                    ->whereHas('statut', function ($s) {
                        $s->whereIn('value', ['EN_COURS', 'TERMINEE']);
                    });
            })
            ->orderBy('date_limite')
            ->get();
    }


    /**
     * Soumettre un rendu de projet.
     *
     * L'apprenant peut soumettre un projet qui lui avait été attribué
     * même si ce projet a été désactivé après son inscription.
     *
     * Seuls les rendus A_FAIRE, A_REPRENDRE et EN_RETARD
     * peuvent être soumis.
     */
    public function soumettre(
        Utilisateur $utilisateur,
        RenduProjet $rendu,
        string $lienGithub
    ): RenduProjet {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        // Vérifier que le rendu appartient bien à cet apprenant.
        if ($rendu->apprenant_id !== $utilisateur->id) {
            throw new RegleMetierException(
                'Ce rendu ne vous appartient pas.',
                404
            );
        }

        $rendu->loadMissing('statut', 'projet');

        // Un projet désactivé reste accessible à son ancien inscrit.
        // On ne vérifie donc plus le champ actif du projet.

        // Un rendu EN_ATTENTE ne peut pas encore être soumis.
        if (! $rendu->aStatut(...self::A_FAIRE)) {
            throw new RegleMetierException(
                'Ce rendu ne peut pas encore être soumis.',
                422
            );
        }

        $rendu->update([
            'lien_github' => $lienGithub,
            'date_rendu' => now(),
            'statut_id' => CodeList::idOf('StatutRendu', 'RENDU'),
        ]);

        return $rendu->fresh(['statut', 'projet']);
    }

    /**
     * Actualiser les retards.
     *
     * Les rendus A_FAIRE et A_REPRENDRE dont la date limite
     * est dépassée passent à EN_RETARD.
     *
     * Les projets désactivés sont également concernés si leur rendu
     * avait déjà été attribué à l'apprenant.
     *
     * Les rendus EN_ATTENTE ne sont jamais concernés.
     */
    public function actualiserRetards(?int $apprenantId = null): int
    {
        $requete = RenduProjet::whereHas(
            'statut',
            fn ($q) => $q->whereIn('value', [
                'A_FAIRE',
                'A_REPRENDRE',
            ])
        )
            ->whereDate('date_limite', '<', today());

        if ($apprenantId !== null) {
            $requete->where('apprenant_id', $apprenantId);
        }

        return $requete->update([
            'statut_id' => CodeList::idOf('StatutRendu', 'EN_RETARD'),
        ]);
    }
}
