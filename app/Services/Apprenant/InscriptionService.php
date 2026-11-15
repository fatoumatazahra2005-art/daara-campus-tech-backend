<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\Utilisateur;

class InscriptionService
{
    public function __construct(private AccesService $acces, private ParcoursSuiviService $parcours)
    {
    }

    /** Apprenant.sInscrire(cours) : l'abonnement doit donner accès, une seule inscription par cours. */
    public function sInscrire(Utilisateur $utilisateur, Cours $cours): InscriptionCours
    {
        $this->acces->exigerAbonnementActif($utilisateur->id);

        $cours->loadMissing('statut');

        if ($cours->statut?->value !== 'PUBLIE') {
            throw new RegleMetierException('Ce cours n’est pas disponible.', 404);
        }

        if (InscriptionCours::where('apprenant_id', $utilisateur->id)->where('cours_id', $cours->id)->exists()) {
            throw new RegleMetierException('Vous êtes déjà inscrit à ce cours.', 409);
        }

        return InscriptionCours::create([
            'date_inscription' => today(),
            'progression' => 0,
            'statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS'),
            'apprenant_id' => $utilisateur->id,
            'cours_id' => $cours->id,
        ])->load('statut', 'cours');
    }

    public function abandonner(InscriptionCours $inscription): InscriptionCours
    {
        if (! $inscription->aStatut('EN_COURS')) {
            throw new RegleMetierException('Seule une inscription en cours peut être abandonnée.', 422);
        }

        $inscription->update(['statut_id' => CodeList::idOf('StatutInscription', 'ABANDONNEE')]);
        $this->parcours->recalculerPourInscriptionCours($inscription);

        return $inscription->fresh(['statut', 'cours']);
    }

    /** InscriptionCours.reprendre() : la progression est conservée. */
    public function reprendre(InscriptionCours $inscription): InscriptionCours
    {
        if (! $inscription->aStatut('ABANDONNEE')) {
            throw new RegleMetierException('Seule une inscription abandonnée peut être reprise.', 422);
        }

        $inscription->update(['statut_id' => CodeList::idOf('StatutInscription', 'EN_COURS')]);

        return $inscription->fresh(['statut', 'cours']);
    }

    /** Apprenant.consulterProgression() */
    public function progression(Utilisateur $utilisateur): array
    {
        return [
            'cours' => InscriptionCours::with(['statut', 'cours', 'certificat'])
                ->where('apprenant_id', $utilisateur->id)->orderByDesc('id')->get(),
            'parcours' => \App\Models\InscriptionParcours::with(['statut', 'parcours'])
                ->where('apprenant_id', $utilisateur->id)->orderByDesc('id')->get(),
        ];
    }
}
