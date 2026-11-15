<?php

namespace App\Services;

use App\Models\Abonnement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AbonnementService
{
    public function lister(array $filtres = []): LengthAwarePaginator
    {
        $query = Abonnement::with([
            'statut',
            'apprenant.utilisateur',
            'formule.periodicite',
        ]);

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $query->whereHas('apprenant.utilisateur', function ($q) use ($recherche) {
                $q->where('nom', 'ilike', $recherche)
                    ->orWhere('prenom', 'ilike', $recherche)
                    ->orWhere('email', 'ilike', $recherche);
            });
        }

        if (!empty($filtres['statut'])) {
            $query->whereHas('statut', fn ($q) => $q->where('value', $filtres['statut']));
        }

        return $query->latest()
            ->paginate($filtres['par_page'] ?? 10)
            ->withQueryString();
    }

    public function details(int $id): Abonnement
    {
        return Abonnement::with([
            'statut',
            'apprenant.utilisateur',
            'formule.periodicite',
            'periodes.formule',
            'periodes.paiements.statut',
            'periodes.paiements.methode',
        ])->findOrFail($id);
    }
}
