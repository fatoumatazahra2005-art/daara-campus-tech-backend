<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Parcours;
use App\Models\Projet;
use App\Services\Apprenant\ParcoursSuiviService;
use Illuminate\Support\Facades\DB;

/**
 * Projets finaux des parcours, gérés par l'administrateur.
 *
 * Règle 5  : un parcours publié garde toujours au moins un projet actif.
 * Règle 29 : seuls les projets actifs sont attribués et exigés. Un projet déjà attribué (au moins un rendu)
 *            n'est pas supprimé : il est désactivé, et ses rendus sont conservés.
 * Parcours publié : après chaque changement (ajout, activation, désactivation), les inscriptions EN_COURS
 * sont recalculées ; une inscription au parcours déjà TERMINEE reste acquise.
 */
class ProjetService
{
    public function __construct(private readonly ParcoursSuiviService $suivi)
    {
    }

    /**
     * Lister les projets : ?recherche=…&parcours_id=…&actif=0|1
     */
    public function lister(array $filtres = [], int $parPage = 10)
    {
        $parPage = max(1, min($parPage, 50));

        $requete = Projet::with('parcours.statut')->withCount('rendus');

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
            $requete->where('actif', filter_var($filtres['actif'], FILTER_VALIDATE_BOOLEAN));
        }

        return $requete
            ->orderByDesc('actif')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();
    }

    /**
     * Consulter un projet (avec le nombre de rendus, pas les rendus eux-mêmes).
     */
    public function consulter(int $id): Projet
    {
        $projet = Projet::with('parcours.statut')->withCount('rendus')->find($id);

        if (!$projet) {
            throw new RegleMetierException('Projet introuvable.', 404);
        }

        return $projet;
    }

    /**
     * Créer un projet (actif) dans un parcours non archivé.
     * Parcours publié : le projet est attribué aux inscrits qui ont déjà terminé les cours obligatoires (cas 30).
     */
    public function creer(int $parcoursId, array $donnees): Projet
    {
        [$projet, $publie] = DB::transaction(function () use ($parcoursId, $donnees) {

            $parcours = Parcours::with('statut')->whereKey($parcoursId)->lockForUpdate()->first();

            if (!$parcours) {
                throw new RegleMetierException('Parcours introuvable.', 404);
            }

            $this->exigerParcoursModifiable($parcours);

            // L'état actif se change par activer() / desactiver(), pas à la création.
            unset($donnees['actif']);

            $projet = $parcours->projets()->create($donnees);

            return [$projet, $parcours->statut?->value === 'PUBLIE'];
        });

        if ($publie) {
            $this->suivi->recalculerParcours($projet->parcours_id);
        }

        return $this->consulter($projet->id);
    }

    /**
     * Modifier un projet (parcours non archivé).
     */
    public function modifier(int $id, array $donnees): Projet
    {
        return DB::transaction(function () use ($id, $donnees) {

            $projet = $this->trouverVerrouille($id);
            $this->exigerParcoursModifiable($projet->parcours);

            // Un projet ne change pas de parcours ; l'état actif passe par activer() / desactiver().
            unset($donnees['parcours_id'], $donnees['actif']);

            $projet->update($donnees);

            return $this->consulter($projet->id);
        });
    }

    /**
     * Retirer un projet (règle 29) :
     * - jamais attribué : suppression réelle (refusée s'il est le dernier projet actif d'un parcours publié) ;
     * - déjà attribué : désactivation, ses rendus sont conservés.
     *
     * @return string « supprime » ou « desactive », pour le message affiché à l'administrateur
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
                $this->exigerAutreProjetActif($projet, $parcours, 'supprimer');
            }

            $projet->delete();
        });

        return 'supprime';
    }

    /**
     * Projet.desactiver() : il n'est plus attribué ni exigé ; ses rendus sont conservés.
     * Les inscrits EN_COURS sont recalculés (le parcours peut passer TERMINEE).
     */
    public function desactiver(int $id): Projet
    {
        $projet = DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);
            $parcours = $projet->parcours;

            $this->exigerParcoursModifiable($parcours);

            if (!$projet->actif) {
                return $projet; // Déjà désactivé.
            }

            $this->exigerAutreProjetActif($projet, $parcours, 'désactiver');

            $projet->update(['actif' => false]);

            return $projet;
        });

        $this->recalculerSiPublie($projet);

        return $this->consulter($projet->id);
    }

    /**
     * Projet.activer() : le projet est de nouveau attribué et exigé.
     * Les inscrits EN_COURS qui ont terminé les cours obligatoires le reçoivent.
     */
    public function activer(int $id): Projet
    {
        $projet = DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);
            $this->exigerParcoursModifiable($projet->parcours);

            if (!$projet->actif) {
                $projet->update(['actif' => true]);
            }

            return $projet;
        });

        $this->recalculerSiPublie($projet);

        return $this->consulter($projet->id);
    }

    // =====================================================================
    // Outils internes
    // =====================================================================

    private function trouverVerrouille(int $id): Projet
    {
        $projet = Projet::with('parcours.statut')->whereKey($id)->lockForUpdate()->first();

        if (!$projet) {
            throw new RegleMetierException('Projet introuvable.', 404);
        }

        return $projet;
    }

    private function exigerParcoursModifiable(?Parcours $parcours): void
    {
        if ($parcours?->statut?->value === 'ARCHIVE') {
            throw new RegleMetierException('Ce parcours est archivé : ses projets ne peuvent plus être modifiés.');
        }
    }

    /** Règle 5 : un parcours publié garde toujours au moins un projet actif. */
    private function exigerAutreProjetActif(Projet $projet, ?Parcours $parcours, string $action): void
    {
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

    private function recalculerSiPublie(Projet $projet): void
    {
        $projet->loadMissing('parcours.statut');

        if ($projet->parcours?->statut?->value === 'PUBLIE') {
            $this->suivi->recalculerParcours($projet->parcours_id);
        }
    }
}
