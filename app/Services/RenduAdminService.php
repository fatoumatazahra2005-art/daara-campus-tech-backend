<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\Encadreur;
use App\Models\RenduProjet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Apprenant\RenduProjetService;

/**
 * Rendus de projet vus par l'administrateur, et affectation d'un correcteur (RG35).
 */
class RenduAdminService
{

    public function __construct(private RenduProjetService $rendusApprenant) {}
    /** Vues de la liste (onglets de la page admin). */
    public const VUES = ['a_affecter', 'en_correction', 'a_reprendre', 'en_retard', 'corriges', 'a_faire', 'tous'];

    private const RELATIONS = [
        'statut',
        'projet.parcours',
        'apprenant.utilisateur',
        'correcteur.utilisateur',
    ];

    // =====================================================================
    // Lecture
    // =====================================================================

    public function lister(array $filtres = []): LengthAwarePaginator
    {
        $this->rendusApprenant->actualiserRetards();
        $requete = RenduProjet::with(self::RELATIONS);

        $this->appliquerVue($requete, $filtres['vue'] ?? 'a_affecter');

        if (!empty($filtres['recherche'])) {
            $r = '%' . trim($filtres['recherche']) . '%';
            $requete->where(function (Builder $q) use ($r) {
                $q->whereHas('projet', fn ($p) => $p->where('titre', 'ilike', $r))
                    ->orWhereHas('apprenant.utilisateur', fn ($u) => $u->where('nom', 'ilike', $r)
                        ->orWhere('prenom', 'ilike', $r)
                        ->orWhere('email', 'ilike', $r));
            });
        }

        if (!empty($filtres['parcours_id'])) {
            $requete->whereHas('projet', fn ($p) => $p->where('parcours_id', $filtres['parcours_id']));
        }

        if (!empty($filtres['correcteur_id'])) {
            $requete->where('correcteur_id', $filtres['correcteur_id']);
        }

        // Les plus anciens rendus d'abord : premier soumis, premier corrigé.
        return $requete
            ->orderByRaw('date_rendu IS NULL')
            ->orderBy('date_rendu')
            ->orderBy('date_limite')
            ->paginate((int) ($filtres['par_page'] ?? 15))
            ->withQueryString();
    }

    /** Nombre de rendus par onglet. */
    public function compteurs(): array
    {
        $this->rendusApprenant->actualiserRetards();
        return collect(self::VUES)->mapWithKeys(function ($vue) {
            $q = RenduProjet::query();
            $this->appliquerVue($q, $vue);
            return [$vue => $q->count()];
        })->all();
    }

    public function details(int $id): RenduProjet
    {
        $rendu = RenduProjet::with(self::RELATIONS)->find($id);

        if (!$rendu) {
            throw new RegleMetierException('Rendu introuvable.', 404);
        }

        return $rendu;
    }

    // =====================================================================
    // RG35 : suggestion et affectation d'un correcteur
    // =====================================================================

    /**
     * Encadreurs actifs, classés : auteurs des cours du parcours, puis spécialité
     * correspondante, puis les autres ; à égalité, le moins chargé d'abord.
     */
    public function suggestions(int $renduId): Collection
    {
        $rendu = $this->details($renduId);
        $parcours = $rendu->projet?->parcours;

        $coursDuParcours = $parcours
            ? Cours::with('technologie')->whereIn('id', $parcours->cours()->pluck('cours.id'))->get()
            : collect();

        $auteurs = $coursDuParcours->pluck('auteur_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $technologies = $coursDuParcours->pluck('technologie.nom')->filter()->map(fn ($n) => mb_strtolower($n))->unique();

        $statutRendu = CodeList::idOf('StatutRendu', 'RENDU');
        $tableEncadreurs = (new Encadreur())->getTable();
        $tableRendus = (new RenduProjet())->getTable();

        $encadreurs = Encadreur::query()
            ->select("{$tableEncadreurs}.*")
            ->selectSub(
                RenduProjet::query()
                    ->selectRaw('count(*)')
                    ->whereColumn("{$tableRendus}.correcteur_id", "{$tableEncadreurs}.id")
                    ->where('statut_id', $statutRendu),
                'charge'
            )
            ->with('utilisateur')
            ->whereHas('utilisateur.statutCompte', fn ($q) => $q->where('value', 'ACTIF'))
            ->get();

        return $encadreurs
            ->map(function ($e) use ($auteurs, $technologies, $rendu) {
                $specialite = mb_strtolower((string) $e->specialite);
                $correspond = $specialite !== ''
                    && $technologies->contains(fn ($t) => str_contains($specialite, $t) || str_contains($t, $specialite));

                return [
                    'id' => $e->id,
                    'nom' => trim(($e->utilisateur->prenom ?? '') . ' ' . ($e->utilisateur->nom ?? '')),
                    'email' => $e->utilisateur->email ?? null,
                    'specialite' => $e->specialite,
                    'charge' => (int) $e->charge,
                    'est_auteur' => $auteurs->contains((int) $e->id),
                    'specialite_correspond' => $correspond,
                    'est_correcteur_actuel' => (int) $rendu->correcteur_id === (int) $e->id,
                ];
            })
            ->sortBy(fn ($e) => [
                $e['est_auteur'] ? 0 : 1,
                $e['specialite_correspond'] ? 0 : 1,
                $e['charge'],
                $e['nom'],
            ])
            ->values();
    }

    /**
     * Affecter (ou réaffecter) un correcteur à un rendu.
     */
    public function affecter(int $renduId, int $encadreurId): RenduProjet
    {
        return DB::transaction(function () use ($renduId, $encadreurId) {

            $rendu = RenduProjet::with('statut')->whereKey($renduId)->lockForUpdate()->first();

            if (!$rendu) {
                throw new RegleMetierException('Rendu introuvable.', 404);
            }

            $statut = $rendu->statut?->value;

            if ($statut === 'CORRIGE') {
                throw new RegleMetierException('Ce rendu est déjà corrigé : il ne peut plus être réaffecté.');
            }

            if (!in_array($statut, ['RENDU', 'A_REPRENDRE'], true)) {
                throw new RegleMetierException('Seul un rendu soumis par l’apprenant peut être affecté à un correcteur.');
            }

            $encadreur = Encadreur::with('utilisateur.statutCompte')->find($encadreurId);

            if (!$encadreur) {
                throw new RegleMetierException('Encadreur introuvable.', 404);
            }

            if ($encadreur->utilisateur?->statutCompte?->value !== 'ACTIF') {
                throw new RegleMetierException('Cet encadreur n’est pas actif : il ne peut pas corriger de rendu.');
            }

            $rendu->update(['correcteur_id' => $encadreur->id]);

            return $this->details($rendu->id);
        });
    }

    // =====================================================================
    // Vues
    // =====================================================================

    private function appliquerVue(Builder $q, string $vue): void
    {
        $statut = fn (string ...$valeurs) => fn ($s) => $s->whereIn('value', $valeurs);

        match ($vue) {
            'a_affecter'    => $q->whereHas('statut', $statut('RENDU'))->whereNull('correcteur_id'),
            'en_correction' => $q->whereHas('statut', $statut('RENDU'))->whereNotNull('correcteur_id'),
            'a_reprendre'   => $q->whereHas('statut', $statut('A_REPRENDRE')),
            'corriges'      => $q->whereHas('statut', $statut('CORRIGE')),
            'a_faire'       => $q->whereHas('statut', $statut('A_FAIRE')),
            // actualiserRetards() est appelé avant : les retards ont déjà le statut EN_RETARD.
            'en_retard'     => $q->whereHas('statut', $statut('EN_RETARD')),
            default         => null, // 'tous'
        };
    }
}
