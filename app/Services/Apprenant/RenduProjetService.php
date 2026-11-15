<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\RenduProjet;
use App\Models\Utilisateur;

/** Rendus des projets de parcours, côté apprenant (la correction relève de l'encadreur). */
class RenduProjetService
{
    public function __construct(private AccesService $acces)
    {
    }

    public function mesRendus(Utilisateur $utilisateur)
    {
        $this->actualiserRetards($utilisateur->id);

        return RenduProjet::with(['statut', 'projet'])->where('apprenant_id', $utilisateur->id)->orderBy('date_limite')->get();
    }

    /** RenduProjet.soumettre() : enregistre le dépôt GitHub (seul livrable), statut RENDU. */
    public function soumettre(Utilisateur $utilisateur, RenduProjet $rendu, string $lienGithub): RenduProjet
    {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        $rendu->loadMissing('statut');

        if (! $rendu->aStatut('A_FAIRE', 'A_REPRENDRE', 'EN_RETARD')) {
            throw new RegleMetierException('Ce rendu ne peut plus être soumis.', 422);
        }

        $rendu->update([
            'lien_github' => $lienGithub,
            'date_rendu' => now(),
            'statut_id' => CodeList::idOf('StatutRendu', 'RENDU'),
        ]);

        return $rendu->fresh(['statut', 'projet']);
    }

    /** A_FAIRE / A_REPRENDRE dont la date limite est passée deviennent EN_RETARD (soumission encore possible). */
    public function actualiserRetards(?int $apprenantId = null): int
    {
        $requete = RenduProjet::whereHas('statut', fn ($q) => $q->whereIn('value', ['A_FAIRE', 'A_REPRENDRE']))
            ->whereDate('date_limite', '<', today());

        if ($apprenantId) {
            $requete->where('apprenant_id', $apprenantId);
        }

        return $requete->update(['statut_id' => CodeList::idOf('StatutRendu', 'EN_RETARD')]);
    }
}
