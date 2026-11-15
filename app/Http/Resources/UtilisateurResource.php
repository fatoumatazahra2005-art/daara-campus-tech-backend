<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Utilisateur */
class UtilisateurResource extends JsonResource
{
    /**
     * Couleurs de fond des avatars.
     */
    private const COULEURS_AVATAR = [
        '#1E88E5',
        '#43A047',
        '#E53935',
        '#8E24AA',
        '#F4511E',
        '#00897B',
        '#3949AB',
        '#6D4C41',
    ];

    public function toArray(Request $request): array
    {
        // Récupération du prénom et du nom
        $prenom = trim($this->prenom ?? '');
        $nom = trim($this->nom ?? '');

        // Génération des initiales
        $initiales = mb_strtoupper(
            mb_substr($prenom, 0, 1)
            . mb_substr($nom, 0, 1)
        );

        // Couleur stable selon l'identifiant
        $couleur = self::COULEURS_AVATAR[
        $this->id % count(self::COULEURS_AVATAR)
        ];

        // Informations spécifiques au rôle
        $profil = [];

        if (
            $this->role?->value === 'APPRENANT'
            && $this->relationLoaded('apprenant')
            && $this->apprenant
        ) {
            $profil = [
                'niveau' => $this->apprenant->niveau?->value,
            ];
        }

        if (
            $this->role?->value === 'ENCADREUR'
            && $this->relationLoaded('encadreur')
            && $this->encadreur
        ) {
            $profil = [
                'specialite' => $this->encadreur->specialite,
            ];
        }

        return [
            // Informations communes
            'id' => $this->id,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
            'email' => $this->email,
            'telephone' => $this->telephone,

            // Rôle et statut
            'role' => $this->role?->value,
            'statut' => $this->statutCompte?->value,

            // Avatar
            'initiales' => $initiales,
            'couleur_avatar' => $couleur,

            // Informations spécifiques au rôle
            'profil' => $profil,

            // Date de création
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
