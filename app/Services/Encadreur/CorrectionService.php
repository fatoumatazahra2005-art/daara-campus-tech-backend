<?php

namespace App\Services\Encadreur;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\InscriptionParcours;
use App\Models\RenduProjet;
use App\Models\Utilisateur;
use App\Services\Apprenant\ParcoursSuiviService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Correction des rendus de projet par l'encadreur affecté (RG35). */
class CorrectionService
{
    public const VUES = ['a_corriger', 'corriges', 'a_reprendre', 'tous'];

    private const RELATIONS = ['statut', 'projet.parcours', 'apprenant.utilisateur'];

    public function __construct(private ParcoursSuiviService $suivi) {}

    public function lister(Utilisateur $encadreur, array $filtres = []): LengthAwarePaginator
    {
        $q = RenduProjet::with(self::RELATIONS)->where('correcteur_id', $encadreur->id);

        match ($filtres['vue'] ?? 'a_corriger') {
            'a_corriger'  => $q->whereHas('statut', fn ($s) => $s->where('value', 'RENDU')),
            'corriges'    => $q->whereHas('statut', fn ($s) => $s->where('value', 'CORRIGE')),
            'a_reprendre' => $q->whereHas('statut', fn ($s) => $s->whereIn('value', ['A_REPRENDRE', 'EN_RETARD'])),
            default       => null,
        };

        return $q->orderByRaw('date_rendu IS NULL')
            ->orderBy('date_rendu')
            ->paginate((int) ($filtres['par_page'] ?? 15))
            ->withQueryString();
    }

    public function compteurs(Utilisateur $encadreur): array
    {
        $base = fn () => RenduProjet::where('correcteur_id', $encadreur->id);

        return [
            'a_corriger'  => $base()->whereHas('statut', fn ($s) => $s->where('value', 'RENDU'))->count(),
            'a_reprendre' => $base()->whereHas('statut', fn ($s) => $s->whereIn('value', ['A_REPRENDRE', 'EN_RETARD']))->count(),
            'corriges'    => $base()->whereHas('statut', fn ($s) => $s->where('value', 'CORRIGE'))->count(),
            'tous'        => $base()->count(),
        ];
    }

    public function details(Utilisateur $encadreur, int $id): RenduProjet
    {
        $rendu = RenduProjet::with(self::RELATIONS)->where('correcteur_id', $encadreur->id)->find($id);

        // Introuvable aussi si le rendu est affecté à un autre encadreur.
        if (!$rendu) {
            throw new RegleMetierException('Rendu introuvable.', 404);
        }

        return $rendu;
    }

    /**
     * Noter un rendu : CORRIGE si note ≥ note minimale du projet, sinon A_REPRENDRE
     * avec une nouvelle date limite. Le parcours de l'apprenant est ensuite recalculé.
     */
    public function corriger(Utilisateur $encadreur, int $id, float $note, string $commentaire): RenduProjet
    {
        return DB::transaction(function () use ($encadreur, $id, $note, $commentaire) {

            $rendu = RenduProjet::with(['statut', 'projet'])
                ->where('correcteur_id', $encadreur->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$rendu) {
                throw new RegleMetierException('Rendu introuvable.', 404);
            }

            if ($rendu->statut?->value !== 'RENDU') {
                throw new RegleMetierException('Seul un rendu soumis par l’apprenant peut être corrigé.');
            }

            $noteMinimale = (float) ($rendu->projet?->note_minimale ?? 10);
            $valide = $note >= $noteMinimale;

            $donnees = [
                'note' => $note,
                'commentaire' => $commentaire,
                'statut_id' => CodeList::idOf('StatutRendu', $valide ? 'CORRIGE' : 'A_REPRENDRE'),
            ];

            if (!$valide) {
                // Nouveau délai pour reprendre le projet.
                $donnees['date_limite'] = today()->addDays(max(1, (int) ($rendu->projet?->delai_jours ?? 7)));
            }

            $rendu->update($donnees);

            // Parcours terminé si tous ses rendus sont désormais corrigés.
            if ($valide && $rendu->projet) {
                InscriptionParcours::where('apprenant_id', $rendu->apprenant_id)
                    ->where('parcours_id', $rendu->projet->parcours_id)
                    ->get()
                    ->each(fn ($inscription) => $this->suivi->recalculer($inscription));
            }

            return $rendu->fresh(self::RELATIONS);
        });
    }
}
