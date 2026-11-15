<?php

namespace App\Policies;

use App\Models\Technologie;
use App\Models\Utilisateur;

class TechnologiePolicy
{
    /**
     * Consulter la liste des technologies.
     */
    public function viewAny(Utilisateur $user): bool
    {
        return in_array(
            $user->role?->value,
            ['ADMIN', 'ENCADREUR', 'APPRENANT'],
            true
        );
    }

    /**
     * Consulter une technologie.
     */
    public function view(
        Utilisateur $user,
        Technologie $technologie
    ): bool {
        return in_array(
            $user->role?->value,
            ['ADMIN', 'ENCADREUR', 'APPRENANT'],
            true
        );
    }

    /**
     * Créer une technologie.
     */
    public function create(Utilisateur $user): bool
    {
        return $user->aLeRole('ADMIN');
    }

    /**
     * Modifier une technologie.
     */
    public function update(
        Utilisateur $user,
        Technologie $technologie
    ): bool {
        return $user->aLeRole('ADMIN');
    }

    /**
     * Supprimer une technologie.
     */
    public function delete(
        Utilisateur $user,
        Technologie $technologie
    ): bool {
        return $user->aLeRole('ADMIN');
    }
}
