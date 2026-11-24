<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Parcours;
use App\Models\Projet;
use Illuminate\Support\Facades\DB;

/**
 * Projets finaux des parcours, gérés par l'administrateur.
 *
 * Règle 5 : un parcours publié garde toujours au moins un projet actif.
 *
 * Règle 29 :
 * - Les projets actifs au moment de l'inscription sont attribués à l'apprenant.
 * - Un projet déjà attribué (au moins un rendu) n'est pas supprimé :
 *   il est désactivé et ses rendus sont conservés.
 * - Ajouter, activer ou désactiver un projet ne modifie pas les projets
 *   déjà attribués aux apprenants inscrits.
 * - Les nouveaux inscrits reçoivent uniquement les projets actifs
 *   au moment de leur inscription.
 */
class ProjetService
{
    /**
     * Lister les projets : ?recherche=…&parcours_id=…&actif=0|1
     */
    public function lister(array $filtres = [], int $parPage = 10)
    {
        $parPage = max(1, min($parPage, 50));

        $requete = Projet::with('parcours.statut')
            ->withCount('rendus');

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $requete->where(function ($query) use ($recherche) {
                $query->where('titre', 'ilike', $recherche)
                    ->orWhere('consigne', 'ilike', $recherche);
            });
        }

        if (!empty($filtres['parcours_id'])) {
            $requete->where('parcours_id', $filtres['parcours_id']);
        }

        if (isset($filtres['actif'])) {
            $requete->where(
                'actif',
                filter_var($filtres['actif'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        return $requete
            ->orderByDesc('actif')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();
    }

    /**
     * Consulter un projet avec le nombre de rendus.
     */
    public function consulter(int $id): Projet
    {
        $projet = Projet::with('parcours.statut')
            ->withCount('rendus')
            ->find($id);

        if (!$projet) {
            throw new RegleMetierException('Projet introuvable.', 404);
        }

        return $projet;
    }

    /**
     * Créer un projet actif dans un parcours non archivé.
     *
     * Le projet sera attribué uniquement aux apprenants
     * qui s'inscriront au parcours après sa création.
     *
     * Les inscriptions existantes ne sont pas modifiées.
     */
    public function creer(int $parcoursId, array $donnees): Projet
    {
        $projet = DB::transaction(function () use ($parcoursId, $donnees) {

            $parcours = Parcours::with('statut')
                ->whereKey($parcoursId)
                ->lockForUpdate()
                ->first();

            if (!$parcours) {
                throw new RegleMetierException('Parcours introuvable.', 404);
            }

            $this->exigerParcoursModifiable($parcours);

            // L'état actif se change par activer() / desactiver(),
            // pas à la création.
            unset($donnees['actif']);

            return $parcours->projets()->create($donnees);
        });

        return $this->consulter($projet->id);
    }

    /**
     * Modifier un projet appartenant à un parcours non archivé.
     */
    public function modifier(int $id, array $donnees): Projet
    {
        return DB::transaction(function () use ($id, $donnees) {

            $projet = $this->trouverVerrouille($id);

            $this->exigerParcoursModifiable($projet->parcours);

            // Un projet ne change pas de parcours.
            // L'état actif passe par activer() / desactiver().
            unset($donnees['parcours_id'], $donnees['actif']);

            $projet->update($donnees);

            return $this->consulter($projet->id);
        });
    }

    /**
     * Retirer un projet (règle 29) :
     *
     * - Jamais attribué : suppression réelle.
     * - Déjà attribué : désactivation, ses rendus sont conservés.
     *
     * @return string "supprime" ou "desactive"
     */
    public function supprimer(int $id): string
    {
        $projet = $this->consulter($id);

        if ($projet->rendus_count > 0) {
            $this->desactiver($id);

            return 'desactive';
        }

        DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);
            $parcours = $projet->parcours;

            $this->exigerParcoursModifiable($parcours);

            if ($projet->actif) {
                $this->exigerAutreProjetActif(
                    $projet,
                    $parcours,
                    'supprimer'
                );
            }

            $projet->delete();
        });

        return 'supprime';
    }

    /**
     * Désactiver un projet.
     *
     * Les rendus déjà attribués restent conservés.
     * Les apprenants déjà inscrits gardent leur accès au projet.
     * Les nouvelles inscriptions ne recevront pas ce projet.
     *
     * Aucun recalcul des inscriptions existantes.
     */
    public function desactiver(int $id): Projet
    {
        DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);
            $parcours = $projet->parcours;

            $this->exigerParcoursModifiable($parcours);

            if (!$projet->actif) {
                return;
            }

            $this->exigerAutreProjetActif(
                $projet,
                $parcours,
                'désactiver'
            );

            $projet->update(['actif' => false]);
        });

        return $this->consulter($id);
    }

    /**
     * Réactiver un projet.
     *
     * Le projet sera disponible pour les nouvelles inscriptions.
     * Les apprenants déjà inscrits ne reçoivent pas ce projet
     * s'il ne leur avait pas été attribué lors de leur inscription.
     *
     * Aucun recalcul des inscriptions existantes.
     */
    public function activer(int $id): Projet
    {
        DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);

            $this->exigerParcoursModifiable($projet->parcours);

            if (!$projet->actif) {
                $projet->update(['actif' => true]);
            }
        });

        return $this->consulter($id);
    }

    // ============================================================
    // Outils internes
    // ============================================================

    private function trouverVerrouille(int $id): Projet
    {
        $projet = Projet::with('parcours.statut')
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        if (!$projet) {
            throw new RegleMetierException('Projet introuvable.', 404);
        }

        return $projet;
    }

    private function exigerParcoursModifiable(?Parcours $parcours): void
    {
        if ($parcours?->statut?->value === 'ARCHIVE') {
            throw new RegleMetierException(
                'Ce parcours est archivé : ses projets ne peuvent plus être modifiés.'
            );
        }
    }

    /**
     * Règle 5 :
     * Un parcours publié garde toujours au moins un projet actif.
     */
    private function exigerAutreProjetActif(
        Projet $projet,
        ?Parcours $parcours,
        string $action
    ): void {
        if ($parcours?->statut?->value !== 'PUBLIE') {
            return;
        }

        $autres = Projet::where('parcours_id', $parcours->id)
            ->where('id', '!=', $projet->id)
            ->where('actif', true)
            ->exists();

        if (!$autres) {
            throw new RegleMetierException(
                "Impossible de {$action} ce projet : c’est le dernier projet actif de ce parcours publié. "
                . 'Ajoutez ou activez d’abord un autre projet.'
            );
        }
    }
}
