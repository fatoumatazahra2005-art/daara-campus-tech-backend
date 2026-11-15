<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Technologie;
use Illuminate\Database\Eloquent\Collection;

class TechnologieService
{
    /**
     * Lister toutes les technologies, avec le nombre de cours qui les utilisent.
     */
    public function lister(): Collection
    {
        return Technologie::withCount('cours')
            ->orderBy('nom')
            ->get();
    }

    /**
     * Consulter une technologie.
     */
    public function consulter(int $id): Technologie
    {
        $technologie = Technologie::withCount('cours')->find($id);

        if (!$technologie) {
            throw new RegleMetierException('Technologie introuvable.', 404);
        }

        return $technologie;
    }

    /**
     * Créer une technologie.
     */
    public function creer(array $donnees): Technologie
    {
        return Technologie::create($donnees)->loadCount('cours');
    }

    /**
     * Modifier une technologie.
     */
    public function modifier(Technologie $technologie, array $donnees): Technologie
    {
        $technologie->update($donnees);

        return $technologie->refresh()->loadCount('cours');
    }

    /**
     * Supprimer une technologie non utilisée.
     */
    public function supprimer(Technologie $technologie): void
    {
        if ($technologie->cours()->exists()) {
            throw new RegleMetierException(
                'Impossible de supprimer cette technologie : elle est utilisée par '
                . $technologie->cours()->count() . ' cours. Désactivez-la plutôt.'
            );
        }

        if (method_exists($technologie, 'parcours') && $technologie->parcours()->exists()) {
            throw new RegleMetierException(
                'Impossible de supprimer cette technologie : elle est utilisée par des parcours.'
            );
        }

        $technologie->delete();
    }
}
