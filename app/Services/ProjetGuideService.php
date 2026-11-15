<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Chapitre;
use App\Models\Cours;
use App\Models\EtapeProjetGuide;
use App\Models\ProjetGuide;

use Illuminate\Support\Facades\DB;

/**
 * Projet guidé (fil rouge) porté par un cours, avec ses étapes. Il fait partie de la création du cours.
 * Règle 3 : tant que le cours n'a jamais été publié, le projet et ses étapes se suppriment réellement ;
 * une fois le cours publié, ils ne sont plus supprimés (les progressions des apprenants y sont liées).
 */
class ProjetGuideService
{
    public function __construct(private readonly CoursService $cours)
    {
    }

    public function creer(Cours $cours, array $donnees): ProjetGuide
    {
        return DB::transaction(function () use ($cours, $donnees) {
            $projet = ProjetGuide::create([
                'titre' => $donnees['titre'],
                'description' => $donnees['description'] ?? null,
                'image_resultat' => $donnees['image_resultat'] ?? null,
                'cours_id' => $cours->id,
            ]);

            $this->cours->marquerModifie($cours);

            return $projet->refresh();
        });
    }

    public function modifier(ProjetGuide $projet, array $donnees): ProjetGuide
    {
        return DB::transaction(function () use ($projet, $donnees) {
            $projet->update(array_intersect_key($donnees, array_flip(['titre', 'description', 'image_resultat'])));

            // Mêmes données renvoyées : pas de modification, pas de revalidation.
            if ($projet->wasChanged()) {
                $this->cours->marquerModifie($projet->cours);
            }

            return $projet->refresh();
        });
    }

    /** Suppression réelle, seulement si le cours n'a jamais été publié (règle 3). */
    public function supprimer(ProjetGuide $projet): void
    {
        DB::transaction(function () use ($projet) {
            $cours = $projet->cours;

            $this->exigerJamaisPublie(
                $cours,
                'Ce cours a déjà été publié : son projet guidé ne peut plus être supprimé, car des apprenants peuvent l’avoir commencé. Vous pouvez encore modifier ses informations et ses étapes.'
            );

            $projet->delete();
            $this->cours->marquerModifie($cours);
        });
    }

    public function ajouterEtape(ProjetGuide $projet, array $donnees): EtapeProjetGuide
    {
        return DB::transaction(function () use ($projet, $donnees) {
            $this->exigerChapitreDuCours($projet->cours, (int) $donnees['chapitre_id']);

            $etape = EtapeProjetGuide::create([
                'consigne' => $donnees['consigne'],
                'code_depart' => $donnees['code_depart'] ?? null,
                'tests_attendus' => $donnees['tests_attendus'] ?? null,
                'ordre' => (int) EtapeProjetGuide::where('projet_guide_id', $projet->id)->max('ordre') + 1,
                'projet_guide_id' => $projet->id,
                'chapitre_id' => $donnees['chapitre_id'],
            ]);

            $this->cours->marquerModifie($projet->cours);

            return $etape->refresh();
        });
    }

    public function modifierEtape(EtapeProjetGuide $etape, array $donnees): EtapeProjetGuide
    {
        return DB::transaction(function () use ($etape, $donnees) {
            $cours = $etape->projetGuide->cours;

            if (isset($donnees['chapitre_id'])) {
                $this->exigerChapitreDuCours($cours, (int) $donnees['chapitre_id']);
            }

            $etape->update(array_intersect_key($donnees, array_flip(['consigne', 'code_depart', 'tests_attendus', 'chapitre_id'])));

            // Mêmes données renvoyées : pas de modification, pas de revalidation.
            if ($etape->wasChanged()) {
                $this->cours->marquerModifie($cours);
            }

            return $etape->refresh();
        });
    }

    /** Suppression réelle, seulement si le cours n'a jamais été publié (règle 3). */
    public function retirerEtape(EtapeProjetGuide $etape): void
    {
        DB::transaction(function () use ($etape) {
            $cours = $etape->projetGuide->cours;

            $this->exigerJamaisPublie(
                $cours,
                'Ce cours a déjà été publié : cette étape ne peut plus être supprimée, car des apprenants peuvent l’avoir commencée. Vous pouvez encore modifier sa consigne.'
            );

            $etape->delete();
            $this->cours->marquerModifie($cours);
        });
    }

    // =====================================================================
    // Contrôles
    // =====================================================================

    /** Règle 3 : un cours déjà publié (date_publication renseignée) ne perd plus son contenu. */
    private function exigerJamaisPublie(Cours $cours, string $message): void
    {
        if ($cours->date_publication !== null) {
            throw new RegleMetierException($message);
        }
    }

    /** Le chapitre lié à une étape doit appartenir au cours qui porte le projet guidé. */
    private function exigerChapitreDuCours(Cours $cours, int $chapitreId): void
    {
        $appartient = Chapitre::query()
            ->whereKey($chapitreId)
            ->whereHas('module', fn ($q) => $q->where('cours_id', $cours->id))
            ->exists();

        if (! $appartient) {
            throw new RegleMetierException('Le chapitre choisi n\'appartient pas à ce cours.');
        }
    }
}
