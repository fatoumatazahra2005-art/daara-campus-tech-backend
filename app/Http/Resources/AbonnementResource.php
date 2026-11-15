<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbonnementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'statut' => $this->whenLoaded('statut', function () {
                return [
                    'id' => $this->statut->id,
                    'value' => $this->statut->value,
                    'description' => $this->statut->description,
                ];
            }),

            'credits_hors_ligne' => $this->credits_hors_ligne,
            'date_resiliation' => $this->date_resiliation?->format('Y-m-d'),

            'apprenant' => $this->whenLoaded('apprenant', function () {
                return [
                    'id' => $this->apprenant->id,

                    'utilisateur' => $this->apprenant->relationLoaded('utilisateur')
                        ? [
                            'id' => $this->apprenant->utilisateur->id,
                            'nom' => $this->apprenant->utilisateur->nom,
                            'prenom' => $this->apprenant->utilisateur->prenom,
                            'email' => $this->apprenant->utilisateur->email,
                            'telephone' => $this->apprenant->utilisateur->telephone,
                        ]
                        : null,
                ];
            }),

            'formule' => $this->whenLoaded('formule', function () {
                return [
                    'id' => $this->formule->id,
                    'libelle' => $this->formule->libelle,
                    'description' => $this->formule->description,
                    'prix' => $this->formule->prix,
                    'nb_echeances_max' => $this->formule->nb_echeances_max,
                    'quota_hors_ligne' => $this->formule->quota_hors_ligne,
                    'jours_essai' => $this->formule->jours_essai,
                    'active' => $this->formule->active,

                    'periodicite' => $this->formule->relationLoaded('periodicite')
                        ? [
                            'id' => $this->formule->periodicite->id,
                            'value' => $this->formule->periodicite->value,
                        ]
                        : null,
                ];
            }),

            'periodes' => $this->whenLoaded('periodes', function () {
                return $this->periodes->map(function ($periode) {
                    return [
                        'id' => $periode->id,
                        'numero' => $periode->numero,
                        'date_debut' => $periode->date_debut?->format('Y-m-d'),
                        'date_fin' => $periode->date_fin?->format('Y-m-d'),
                        'prix_paye' => $periode->prix_paye,
                        'nb_echeances' => $periode->nb_echeances,
                        'est_essai' => $periode->est_essai,

                        'paiements' => $periode->relationLoaded('paiements')
                            ? $periode->paiements->map(function ($paiement) {
                                return [
                                    'id' => $paiement->id,
                                    'montant' => $paiement->montant,
                                    'date_echeance' => $paiement->date_echeance?->format('Y-m-d'),
                                    'date_paiement' => $paiement->date_paiement?->format('Y-m-d H:i:s'),
                                    'numero_echeance' => $paiement->numero_echeance,
                                    'reference' => $paiement->reference,

                                    'statut' => $paiement->relationLoaded('statut')
                                        ? $paiement->statut->value
                                        : null,

                                    'methode' => $paiement->relationLoaded('methode')
                                        ? $paiement->methode->value
                                        : null,
                                ];
                            })
                            : [],
                    ];
                });
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
