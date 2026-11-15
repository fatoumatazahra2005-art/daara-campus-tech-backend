<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\EtapeProjetGuide;
use App\Models\InscriptionCours;
use App\Models\ProgressionEtapeProjetGuide;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;

class ProgressionEtapeProjetGuideService
{
    public function __construct(private AccesService $acces)
    {
    }

    /**
     * Valider une étape de projet guidé.
     */
    public function terminer(
        Utilisateur $utilisateur,
        EtapeProjetGuide $etape
    ): ProgressionEtapeProjetGuide {

        return DB::transaction(function () use ($utilisateur, $etape) {

            // 1. Charger le projet guidé et le chapitre.
            $etape->loadMissing([
                'projetGuide',
                'chapitre.module',
            ]);

            $coursId = $etape->projetGuide->cours_id;

            // 2. Vérifier que le chapitre appartient au même cours.
            if ($etape->chapitre->module->cours_id !== $coursId) {
                throw new RegleMetierException(
                    'Cette étape ne correspond pas au cours du projet guidé.',
                    422
                );
            }

            // 3. Rechercher l'inscription de l'apprenant.
            $inscription = InscriptionCours::where('apprenant_id', $utilisateur->id)
                ->where('cours_id', $coursId)
                ->lockForUpdate()
                ->first();

            if (! $inscription) {
                throw new RegleMetierException(
                    'Vous n’êtes pas inscrit à ce cours.',
                    403
                );
            }

            // 4. Vérifier que l'inscription est en cours.
            if (! $inscription->aStatut('EN_COURS')) {
                throw new RegleMetierException(
                    'Votre inscription à ce cours n’est pas active.',
                    422
                );
            }

            // 5. Vérifier l'abonnement.
            $this->acces->exigerAbonnementActif($utilisateur->id);

            // 6. Rechercher ou créer la progression de l'étape.
            $progression = ProgressionEtapeProjetGuide::firstOrCreate(
                [
                    'inscription_cours_id' => $inscription->id,
                    'etape_projet_guide_id' => $etape->id,
                ],
                [
                    'est_terminee' => true,
                    'date_validation' => now(),
                ]
            );

            // 7. Si la progression existait mais n'était pas terminée.
            if (! $progression->est_terminee) {
                $progression->update([
                    'est_terminee' => true,
                    'date_validation' => now(),
                ]);
            }

            return $progression->refresh();
        });
    }
}
