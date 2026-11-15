<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\Parcours;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Parcours gérés par l'administrateur.
 * Cycle de vie : BROUILLON → PUBLIE → ARCHIVE (définitif).
 */
class ParcoursService
{
    /** Relations chargées pour le détail (cours avec leur statut, pour signaler ceux non publiés). */
    /** Relations chargées pour le détail (cours avec leur statut, projets avec leur nombre de rendus). */
    private function relationsDetail(): array
    {
        return [
            'statut',
            'cours.statut',
            'cours.technologie',
            'cours.niveau',
            'projets' => fn ($q) => $q->withCount('rendus')->orderBy('id'),
        ];
    }

    private const MIN_COURS_PUBLICATION = 2;

    // =====================================================================
    // Lecture
    // =====================================================================

    /**
     * Lister les parcours : recherche, filtre par statut (?statut=PUBLIE), pagination.
     */
    public function lister(array $filtres = [], int $parPage = 12)
    {
        $parPage = max(1, min($parPage, 50));

        // cours.technologie : nécessaire pour afficher les logos des technologies sur les cartes.
        $requete = Parcours::with(['statut', 'cours.technologie'])
            ->withCount(['cours', 'projets', 'inscriptions']);

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $requete->where(function ($query) use ($recherche) {
                $query->where('titre', 'ilike', $recherche)
                    ->orWhere('description', 'ilike', $recherche);
            });
        }

        // Filtre par valeur (BROUILLON, PUBLIE, ARCHIVE), ou par identifiant pour compatibilité.
        if (!empty($filtres['statut'])) {
            $requete->whereHas('statut', fn ($q) => $q->where('value', $filtres['statut']));
        } elseif (!empty($filtres['statut_id'])) {
            $requete->where('statut_id', $filtres['statut_id']);
        }

        return $requete
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();
    }

    /**
     * Consulter un parcours avec ses cours (dans l'ordre) et ses projets.
     */
    public function consulter(int $id): Parcours
    {
        $parcours = Parcours::with($this->relationsDetail())
            ->withCount('inscriptions')
            ->find($id);

        if (!$parcours) {
            throw new RegleMetierException('Parcours introuvable.', 404);
        }

        return $parcours;
    }

    // =====================================================================
    // Création et modification
    // =====================================================================

    /**
     * Créer un parcours (toujours en brouillon).
     */
    public function creer(array $donnees): Parcours
    {
        return DB::transaction(function () use ($donnees) {

            $donnees = $this->traiterImage($donnees);
            $donnees['statut_id'] = $this->statutId('BROUILLON');
            $donnees['date_creation'] = now()->toDateString();

            $parcours = Parcours::create($donnees);

            return $this->consulter($parcours->id);
        });
    }

    /**
     * Modifier un parcours (impossible une fois archivé).
     */
    public function modifier(int $id, array $donnees): Parcours
    {
        return DB::transaction(function () use ($id, $donnees) {

            $parcours = $this->trouverVerrouille($id);

            if ($this->statutDe($parcours) === 'ARCHIVE') {
                throw new RegleMetierException('Un parcours archivé ne peut plus être modifié.');
            }

            $donnees = $this->traiterImage($donnees, $parcours);

            // Le statut ne change que via publier() / archiver().
            unset($donnees['statut_id'], $donnees['date_creation']);

            $parcours->update($donnees);

            return $this->consulter($parcours->id);
        });
    }

    // =====================================================================
    // Cycle de vie
    // =====================================================================

    /**
     * Publier un parcours : brouillon, au moins 2 cours tous publiés, au moins 1 projet final.
     */
    public function publier(int $id): Parcours
    {
        return DB::transaction(function () use ($id) {

            $parcours = $this->trouverVerrouille($id);
            $parcours->load('cours.statut');

            if ($this->statutDe($parcours) !== 'BROUILLON') {
                throw new RegleMetierException('Seul un parcours en brouillon peut être publié.');
            }

            if ($parcours->cours->count() < self::MIN_COURS_PUBLICATION) {
                throw new RegleMetierException(
                    'Un parcours doit contenir au minimum ' . self::MIN_COURS_PUBLICATION . ' cours pour être publié.'
                );
            }

            // Tous les cours doivent être accessibles aux apprenants.
            $nonPublies = $parcours->cours
                ->filter(fn ($cours) => $cours->statut?->value !== 'PUBLIE')
                ->pluck('titre');

            if ($nonPublies->isNotEmpty()) {
                throw new RegleMetierException(
                    'Ces cours ne sont pas publiés : « ' . $nonPublies->implode(' », « ')
                    . ' ». Retirez-les du parcours ou attendez leur validation.'
                );
            }

            if (!$parcours->projets()->exists()) {
                throw new RegleMetierException('Un parcours doit posséder au moins un projet final pour être publié.');
            }

            $parcours->update(['statut_id' => $this->statutId('PUBLIE')]);

            return $this->consulter($parcours->id);
        });
    }

    /**
     * Archiver un parcours publié : retiré du catalogue, définitif.
     * Les apprenants déjà inscrits conservent leur progression.
     */
    public function archiver(int $id): Parcours
    {
        return DB::transaction(function () use ($id) {

            $parcours = $this->trouverVerrouille($id);

            if ($this->statutDe($parcours) !== 'PUBLIE') {
                throw new RegleMetierException(
                    'Seul un parcours publié peut être archivé. Un brouillon peut être supprimé.'
                );
            }

            $parcours->update(['statut_id' => $this->statutId('ARCHIVE')]);

            return $this->consulter($parcours->id);
        });
    }

    /**
     * Supprimer un parcours : uniquement un brouillon, sans inscription ni projet.
     */
    public function supprimer(int $id): bool
    {
        return DB::transaction(function () use ($id) {

            $parcours = $this->trouverVerrouille($id);

            if ($this->statutDe($parcours) !== 'BROUILLON') {
                throw new RegleMetierException(
                    'Seul un parcours en brouillon peut être supprimé. Un parcours publié doit être archivé.'
                );
            }

            if ($parcours->inscriptions()->exists()) {
                throw new RegleMetierException('Ce parcours ne peut pas être supprimé : des apprenants y sont inscrits.');
            }

            if ($parcours->projets()->exists()) {
                throw new RegleMetierException('Supprimez d\'abord le projet final de ce parcours.');
            }

            if ($parcours->image) {
                Storage::disk('public')->delete($parcours->image);
            }

            $parcours->cours()->detach();

            return (bool) $parcours->delete();
        });
    }

    // =====================================================================
    // Outils internes
    // =====================================================================

    private function trouverVerrouille(int $id): Parcours
    {
        $parcours = Parcours::with('statut')->whereKey($id)->lockForUpdate()->first();

        if (!$parcours) {
            throw new RegleMetierException('Parcours introuvable.', 404);
        }

        return $parcours;
    }

    private function statutDe(Parcours $parcours): ?string
    {
        return $parcours->statut?->value;
    }

    private function statutId(string $valeur): int
    {
        return CodeList::idOf('StatutParcours', $valeur);
    }

    /**
     * Enregistre la nouvelle image si un fichier est envoyé.
     * Sans fichier, l'image actuelle est conservée.
     */
    private function traiterImage(array $donnees, ?Parcours $parcours = null): array
    {
        $image = $donnees['image'] ?? null;

        if ($image instanceof UploadedFile) {
            if ($parcours?->image) {
                Storage::disk('public')->delete($parcours->image);
            }
            $donnees['image'] = $image->store('parcours', 'public');
        } else {
            unset($donnees['image']);
        }

        return $donnees;
    }
}
