<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageForumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contenu' => $this->contenu,
            'date_envoi' => $this->date_envoi?->format('Y-m-d H:i:s'),
            'nb_votes' => $this->nb_votes,
            'nb_signalements' => $this->nb_signalements,
            'est_masque' => $this->est_masque,
            'date_masquage' => $this->date_masquage?->format('Y-m-d H:i:s'),

            'auteur' => $this->whenLoaded('auteur', function () {
                return [
                    'id' => $this->auteur->id,
                    'nom' => $this->auteur->nom,
                    'prenom' => $this->auteur->prenom,
                ];
            }),

            'sujet' => $this->whenLoaded('sujet', function () {
                return [
                    'id' => $this->sujet->id,
                    'titre' => $this->sujet->titre,
                ];
            }),

            'masque_par' => $this->whenLoaded('masquePar', function () {
                return $this->masquePar
                    ? [
                        'id' => $this->masquePar->id,
                        'utilisateur' => $this->masquePar->relationLoaded('utilisateur')
                            ? [
                                'nom' => $this->masquePar->utilisateur->nom,
                                'prenom' => $this->masquePar->utilisateur->prenom,
                            ]
                            : null,
                    ]
                    : null;
            }),
        ];
    }
}
