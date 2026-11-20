<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\RenduProjet;
use App\Models\Utilisateur;

/**
 * Rendus des projets de parcours, côté apprenant (la correction relève de l'encadreur).
 * Règle 29 : un projet désactivé n'est plus exigé. Ses rendus encore à faire disparaissent de la liste de
 * l'apprenant ; ses rendus déjà soumis ou corrigés restent visibles (historique) et sont conservés.
 */
class RenduProjetService
{
    /** Statuts d'un rendu qui reste à faire par l'apprenant. */
    private const A_FAIRE = ['A_FAIRE', 'A_REPRENDRE', 'EN_RETARD'];

    public function __construct(private AccesService $acces)
    {
    }

    /**
     * Rendus de l'apprenant : tous ceux des projets actifs,
     * et, pour un projet désactivé, seulement ceux déjà soumis ou corrigés.
     */
    public function mesRendus(Utilisateur $utilisateur)
    {
        $this->actualiserRetards($utilisateur->id);

        return RenduProjet::with(['statut', 'projet'])
            ->where('apprenant_id', $utilisateur->id)
            ->where(function ($q) {
                $q->whereHas('projet', fn ($p) => $p->where('actif', true))
                    ->orWhereHas('statut', fn ($s) => $s->whereIn('value', ['RENDU', 'CORRIGE']));
            })
            ->orderBy('date_limite')
            ->get();
    }

    /** RenduProjet.soumettre() : enregistre le dépôt GitHub (seul livrable), statut RENDU. */
    public function soumettre(Utilisateur $utilisateur, RenduProjet $rendu, string $lienGithub): RenduProjet
    {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        $rendu->loadMissing('statut', 'projet');

        // Règle 29 : un projet désactivé n'est plus à rendre.
        if ($rendu->projet && ! $rendu->projet->actif) {
            throw new RegleMetierException(
                'Ce projet a été retiré du parcours : il n’est plus à rendre et ne bloque plus la fin du parcours.',
                422
            );
        }

        if (! $rendu->aStatut(...self::A_FAIRE)) {
            throw new RegleMetierException('Ce rendu ne peut plus être soumis.', 422);
        }

        $rendu->update([
            'lien_github' => $lienGithub,
            'date_rendu' => now(),
            'statut_id' => CodeList::idOf('StatutRendu', 'RENDU'),
        ]);

        return $rendu->fresh(['statut', 'projet']);
    }

    /**
     * A_FAIRE / A_REPRENDRE dont la date limite est passée deviennent EN_RETARD (soumission encore possible).
     * Seuls les rendus des projets actifs sont concernés.
     */
    public function actualiserRetards(?int $apprenantId = null): int
    {
        $requete = RenduProjet::whereHas('statut', fn ($q) => $q->whereIn('value', ['A_FAIRE', 'A_REPRENDRE']))
            ->whereHas('projet', fn ($p) => $p->where('actif', true))
            ->whereDate('date_limite', '<', today());

        if ($apprenantId) {
            $requete->where('apprenant_id', $apprenantId);
        }

        return $requete->update(['statut_id' => CodeList::idOf('StatutRendu', 'EN_RETARD')]);
    }
}
