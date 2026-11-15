<?php

namespace App\Policies;

use App\Models\Utilisateur;

class UtilisateurPolicy
{
    /**
     * Consulter la liste des utilisateurs.
     */
    public function viewAny(Utilisateur $u): bool
    {
        return $u->aLeRole('ADMIN');
    }

    /**
     * Créer un utilisateur.
     */
    public function create(Utilisateur $u): bool
    {
        return $u->aLeRole('ADMIN');
    }

    /**
     * Modifier un utilisateur.
     */
    public function update(Utilisateur $u, Utilisateur $cible): bool
    {
        return $u->aLeRole('ADMIN');
    }

    /**
     * Changer le statut d'un utilisateur.
     */
    public function changerStatut(Utilisateur $u, Utilisateur $cible): bool
    {
        return $u->aLeRole('ADMIN');
    }

    /**
     * Supprimer un utilisateur.
     */
    public function delete(Utilisateur $u, Utilisateur $cible): bool
    {
        return $u->aLeRole('ADMIN')
            && $u->id !== $cible->id;
    }
}
