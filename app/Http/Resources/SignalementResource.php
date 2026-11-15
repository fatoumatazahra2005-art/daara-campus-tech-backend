<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SignalementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'motif' => $this->motif,
            'date_signalement' => $this->date_signalement?->format('Y-m-d H:i:s'),
            'traite_par' => $this->whenLoaded('traitePar', fn () => $this->traitePar ? [
                'id' => $this->traitePar->id,
                'nom' => $this->traitePar->utilisateur?->nom,
                'prenom' => $this->traitePar->utilisateur?->prenom,
            ] : null),

            'utilisateur' => $this->whenLoaded('utilisateur', function () {
                return [
                    'id' => $this->utilisateur->id,
                    'nom' => $this->utilisateur->nom,
                    'prenom' => $this->utilisateur->prenom,
                    'email' => $this->utilisateur->email,
                ];
            }),

            'message' => $this->whenLoaded('message', function () {
                return [
                    'id' => $this->message->id,
                    'contenu' => $this->message->contenu,
                    'date_envoi' => $this->message->date_envoi?->format('Y-m-d H:i:s'),
                    'est_masque' => $this->message->est_masque,

                    'auteur' => $this->message->relationLoaded('auteur')
                        ? [
                            'id' => $this->message->auteur->id,
                            'nom' => $this->message->auteur->nom,
                            'prenom' => $this->message->auteur->prenom,
                        ]
                        : null,

                    'sujet' => $this->message->relationLoaded('sujet')
                        ? [
                            'id' => $this->message->sujet->id,
                            'titre' => $this->message->sujet->titre,
                        ]
                        : null,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
