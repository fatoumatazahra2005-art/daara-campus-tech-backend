<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Parcours;
use App\Models\Projet;
use Illuminate\Support\Facades\DB;

/** Projets finaux des parcours, gérés par l'administrateur. */
class ProjetService
{
    /**
     * Lister les projets : ?recherche=…&parcours_id=…
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

        return $requete
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
     * Créer un projet dans un parcours non archivé.
     */
    public function creer(int $parcoursId, array $donnees): Projet
    {
        return DB::transaction(function () use ($parcoursId, $donnees) {

            $parcours = Parcours::with('statut')->whereKey($parcoursId)->lockForUpdate()->first();

            if (!$parcours) {
                throw new RegleMetierException('Parcours introuvable.', 404);
            }

            $this->exigerParcoursModifiable($parcours);

            $projet = $parcours->projets()->create($donnees);

            return $this->consulter($projet->id);
        });
    }

    /**
     * Modifier un projet (parcours non archivé).
     */
    public function modifier(int $id, array $donnees): Projet
    {
        return DB::transaction(function () use ($id, $donnees) {

            $projet = $this->trouverVerrouille($id);
            $this->exigerParcoursModifiable($projet->parcours);

            // Un projet ne change pas de parcours.
            unset($donnees['parcours_id']);

            $projet->update($donnees);

            return $this->consulter($projet->id);
        });
    }

    /**
     * Supprimer un projet sans rendu.
     * Un parcours publié doit garder au moins un projet final.
     */
    public function supprimer(int $id): bool
    {
        return DB::transaction(function () use ($id) {

            $projet = $this->trouverVerrouille($id);
            $parcours = $projet->parcours;

            $this->exigerParcoursModifiable($parcours);

            if ($projet->rendus()->exists()) {
                throw new RegleMetierException(
                    'Ce projet ne peut pas être supprimé : des apprenants ont déjà soumis des rendus.'
                );
            }

            if ($parcours?->statut?->value === 'PUBLIE' && $parcours->projets()->count() <= 1) {
                throw new RegleMetierException(
                    'Impossible : un parcours publié doit garder au moins un projet final.'
                );
            }

            return (bool) $projet->delete();
        });
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
}
