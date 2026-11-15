<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Cours;
use App\Models\Parcours;
use App\Services\Apprenant\ParcoursSuiviService;
use Illuminate\Support\Facades\DB;

/**
 * Composition d'un parcours : ajout, déplacement, retrait de cours, obligatoire / facultatif.
 * L'ordre est toujours continu : 1, 2, 3…
 *
 * Un parcours publié doit rester publiable (règle 5) : au moins 2 cours, au moins un obligatoire,
 * et tous ses cours obligatoires publiés. Après chaque changement de composition d'un parcours publié,
 * les inscrits reçoivent les cours ajoutés et leur progression est recalculée.
 */
class ParcoursCoursService
{
    private const MIN_COURS_PUBLIE = 2;

    public function __construct(
        private ParcoursService $parcoursService,
        private ParcoursSuiviService $suivi,
    ) {}

    /**
     * Ajouter des cours publiés à la fin du parcours (ou à l'ordre demandé).
     *
     * @param array<int, array{cours_id: int, ordre?: int|null, obligatoire?: bool}> $cours
     */
    public function ajouter(int $parcoursId, array $cours): Parcours
    {
        $resultat = DB::transaction(function () use ($parcoursId, $cours) {

            $parcours = $this->parcoursModifiable($parcoursId);

            $ids = collect($cours)->pluck('cours_id')->map(fn ($id) => (int) $id);

            if ($ids->duplicates()->isNotEmpty()) {
                throw new RegleMetierException('Un même cours a été sélectionné plusieurs fois.');
            }

            // Déjà présents ?
            $dejaPresents = $parcours->cours()->whereIn('cours.id', $ids)->pluck('titre');
            if ($dejaPresents->isNotEmpty()) {
                throw new RegleMetierException(
                    'Déjà dans le parcours : « ' . $dejaPresents->implode(' », « ') . ' ».'
                );
            }

            // Uniquement des cours publiés (contrôle serveur, en plus du filtre de l'écran).
            $nonPublies = Cours::with('statut')
                ->whereIn('id', $ids)
                ->get()
                ->filter(fn ($c) => $c->statut?->value !== 'PUBLIE')
                ->pluck('titre');

            if ($nonPublies->isNotEmpty()) {
                throw new RegleMetierException(
                    'Seuls les cours publiés peuvent être ajoutés. Non publiés : « '
                    . $nonPublies->implode(' », « ') . ' ».'
                );
            }

            // Ajout à la fin, puis déplacement si un ordre précis est demandé.
            $prochain = (int) DB::table('parcours_cours')->where('parcours_id', $parcoursId)->max('ordre') + 1;

            foreach ($cours as $element) {
                $parcours->cours()->attach((int) $element['cours_id'], [
                    'ordre' => $prochain++,
                    'obligatoire' => (bool) ($element['obligatoire'] ?? true),
                ]);
            }

            foreach ($cours as $element) {
                if (!empty($element['ordre'])) {
                    $this->deplacer($parcoursId, (int) $element['cours_id'], (int) $element['ordre']);
                }
            }

            return ['publie' => $parcours->statut?->value === 'PUBLIE', 'ids' => $ids->all()];
        });

        // Parcours publié : les inscrits reçoivent les nouveaux cours, puis leur progression est recalculée.
        if ($resultat['publie']) {
            Cours::with('statut')->whereIn('id', $resultat['ids'])->get()
                ->each(fn (Cours $c) => $this->suivi->rattacherCoursPublie($c));

            $this->suivi->recalculerParcours($parcoursId);
        }

        return $this->parcoursService->consulter($parcoursId);
    }

    /**
     * Modifier un cours du parcours : position (ordre) et/ou caractère obligatoire.
     * Changer l'ordre décale les autres cours (glisser-déposer).
     */
    public function modifier(int $parcoursId, int $coursId, array $donnees): Parcours
    {
        $recalculer = DB::transaction(function () use ($parcoursId, $coursId, $donnees) {

            $parcours = $this->parcoursModifiable($parcoursId);
            $this->verifierAppartenance($parcours, $coursId);
            $publie = $parcours->statut?->value === 'PUBLIE';
            $recalculer = false;

            if (array_key_exists('obligatoire', $donnees)) {
                $obligatoire = filter_var($donnees['obligatoire'], FILTER_VALIDATE_BOOLEAN);
                $actuel = (bool) DB::table('parcours_cours')
                    ->where('parcours_id', $parcoursId)->where('cours_id', $coursId)->value('obligatoire');

                if ($obligatoire !== $actuel) {
                    if ($publie && ! $obligatoire && $this->nbObligatoires($parcoursId) <= 1) {
                        throw new RegleMetierException(
                            'C’est le dernier cours obligatoire de ce parcours publié : il doit en garder au moins un.'
                        );
                    }

                    if ($publie && $obligatoire) {
                        $statut = Cours::with('statut')->find($coursId)?->statut?->value;

                        if ($statut !== 'PUBLIE') {
                            throw new RegleMetierException(
                                'Ce cours n’est pas publié actuellement (en validation ou archivé) : '
                                . 'il ne peut pas devenir obligatoire dans un parcours publié.'
                            );
                        }
                    }

                    // Écriture directe dans la table pivot (indépendante du modèle ParcoursCours).
                    DB::table('parcours_cours')
                        ->where('parcours_id', $parcoursId)
                        ->where('cours_id', $coursId)
                        ->update([
                            'obligatoire' => $obligatoire,
                            'updated_at' => now(),
                        ]);

                    $recalculer = $publie;
                }
            }

            if (!empty($donnees['ordre'])) {
                $this->deplacer($parcoursId, $coursId, (int) $donnees['ordre']);
            }

            return $recalculer;
        });

        // Obligatoire ↔ facultatif change ce qu'il faut terminer : progression des inscrits recalculée.
        if ($recalculer) {
            $this->suivi->recalculerParcours($parcoursId);
        }

        return $this->parcoursService->consulter($parcoursId);
    }

    /**
     * Retirer un cours du parcours, puis renuméroter.
     * Les inscriptions des apprenants à ce cours sont conservées (leur progression aussi).
     */
    public function retirer(int $parcoursId, int $coursId): Parcours
    {
        $publie = DB::transaction(function () use ($parcoursId, $coursId) {

            $parcours = $this->parcoursModifiable($parcoursId);
            $this->verifierAppartenance($parcours, $coursId);
            $publie = $parcours->statut?->value === 'PUBLIE';

            if ($publie) {
                // Un parcours publié doit rester publiable.
                if ($parcours->cours()->count() <= self::MIN_COURS_PUBLIE) {
                    throw new RegleMetierException(
                        'Impossible : un parcours publié doit garder au moins ' . self::MIN_COURS_PUBLIE . ' cours.'
                    );
                }

                $estObligatoire = (bool) DB::table('parcours_cours')
                    ->where('parcours_id', $parcoursId)->where('cours_id', $coursId)->value('obligatoire');

                if ($estObligatoire && $this->nbObligatoires($parcoursId) <= 1) {
                    throw new RegleMetierException(
                        'Impossible : c’est le dernier cours obligatoire de ce parcours publié.'
                    );
                }
            }

            $parcours->cours()->detach($coursId);
            $this->renumeroter($parcoursId);

            return $publie;
        });

        // Le cours retiré n'est plus exigé : progression recalculée (le parcours peut passer TERMINEE).
        if ($publie) {
            $this->suivi->recalculerParcours($parcoursId);
        }

        return $this->parcoursService->consulter($parcoursId);
    }

    // =====================================================================
    // Outils internes
    // =====================================================================

    /** Parcours verrouillé, refusé s'il est archivé. */
    private function parcoursModifiable(int $parcoursId): Parcours
    {
        $parcours = Parcours::with('statut')->whereKey($parcoursId)->lockForUpdate()->first();

        if (!$parcours) {
            throw new RegleMetierException('Parcours introuvable.', 404);
        }

        if ($parcours->statut?->value === 'ARCHIVE') {
            throw new RegleMetierException('Un parcours archivé ne peut plus être modifié.');
        }

        return $parcours;
    }

    private function verifierAppartenance(Parcours $parcours, int $coursId): void
    {
        if (!$parcours->cours()->where('cours.id', $coursId)->exists()) {
            throw new RegleMetierException('Ce cours ne fait pas partie de ce parcours.', 404);
        }
    }

    private function nbObligatoires(int $parcoursId): int
    {
        return DB::table('parcours_cours')
            ->where('parcours_id', $parcoursId)
            ->where('obligatoire', true)
            ->count();
    }

    /** Place un cours à la position voulue (bornée), les autres sont décalés. */
    private function deplacer(int $parcoursId, int $coursId, int $position): void
    {
        $ids = $this->idsOrdonnes($parcoursId)->reject(fn ($id) => $id === $coursId)->values();
        $position = max(1, min($position, $ids->count() + 1));
        $ids->splice($position - 1, 0, [$coursId]);

        $this->ecrireOrdre($parcoursId, $ids->all());
    }

    /** Ordre continu 1..n selon l'ordre actuel. */
    private function renumeroter(int $parcoursId): void
    {
        $this->ecrireOrdre($parcoursId, $this->idsOrdonnes($parcoursId)->all());
    }

    private function idsOrdonnes(int $parcoursId)
    {
        return DB::table('parcours_cours')
            ->where('parcours_id', $parcoursId)
            ->orderBy('ordre')
            ->orderBy('cours_id')
            ->pluck('cours_id')
            ->map(fn ($id) => (int) $id);
    }

    /**
     * Écrit l'ordre en deux passes, pour ne pas heurter une éventuelle
     * contrainte d'unicité (parcours_id, ordre) pendant la mise à jour.
     */
    private function ecrireOrdre(int $parcoursId, array $idsOrdonnes): void
    {
        $table = DB::table('parcours_cours')->where('parcours_id', $parcoursId);

        (clone $table)->update(['ordre' => DB::raw('ordre + 100000')]);

        foreach (array_values($idsOrdonnes) as $index => $coursId) {
            (clone $table)->where('cours_id', $coursId)->update(['ordre' => $index + 1]);
        }
    }
}
