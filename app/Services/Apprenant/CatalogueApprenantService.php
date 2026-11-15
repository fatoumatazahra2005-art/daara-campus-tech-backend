<?php

namespace App\Services\Apprenant;

use App\Models\Chapitre;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\Module;
use App\Models\Utilisateur;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catalogue des cours, avec l'état d'inscription de l'apprenant connecté.
 * - Visible par tous, avec ou sans abonnement : titres, descriptions, plan des cours (le contenu reste verrouillé).
 * - Règle 1 : un cours publié modifié (revalidation) disparaît du catalogue pour les nouveaux inscrits,
 *   mais reste visible pour les apprenants déjà inscrits.
 * - Règle 2 : un cours archivé n'apparaît plus au catalogue.
 */
class CatalogueApprenantService
{
    public function __construct(private AccesService $acces)
    {
    }

    /** @param array{technologie_id?: int, niveau?: string, certifiant?: bool, recherche?: string} $f */
    public function lister(Utilisateur $utilisateur, array $f, int $parPage = 12): LengthAwarePaginator
    {
        $recherche = isset($f['recherche']) ? addcslashes($f['recherche'], '%_\\') : null;

        $page = Cours::with(['statut', 'niveau', 'technologie', 'auteur.utilisateur'])
            ->where(fn (Builder $q) => $this->visiblesAuCatalogue($q, $utilisateur->id))
            ->when($f['technologie_id'] ?? null, fn ($q, $v) => $q->where('technologie_id', $v))
            ->when($f['niveau'] ?? null, fn ($q, $v) => $q->whereHas('niveau', fn ($n) => $n->where('value', $v)))
            ->when(isset($f['certifiant']), fn ($q) => $q->where('est_certifiant', (bool) $f['certifiant']))
            ->when($recherche, fn ($q, $v) => $q->where(fn ($w) => $w->where('titre', 'ilike', "%{$v}%")->orWhere('description', 'ilike', "%{$v}%")))
            ->orderByDesc('date_publication')
            ->orderByDesc('id')
            ->paginate(max(1, min($parPage, 50)));

        $inscriptions = $this->inscriptions($utilisateur, $page->pluck('id')->all());
        $acces = $this->acces->aAcces($utilisateur->id);

        return $page->through(fn (Cours $c) => $this->resume($c, $inscriptions[$c->id] ?? null, $acces));
    }

    /** Détail : plan du cours (titres uniquement) et inscription éventuelle. Le contenu des chapitres se lit via /contenu. */
    public function detail(Utilisateur $utilisateur, Cours $cours): array
    {
        $this->acces->exigerCoursAccessible($utilisateur->id, $cours);
        $cours->load(['statut', 'niveau', 'technologie', 'auteur.utilisateur']);

        $modules = Module::where('cours_id', $cours->id)->where('actif', true)->orderBy('ordre')
            ->with(['chapitres' => fn ($q) => $q->where('actif', true)->orderBy('ordre')->withCount(['quiz as a_un_quiz' => fn ($z) => $z->where('actif', true)])])
            ->get();

        $chapitres = $modules->flatMap->chapitres;
        $inscription = $this->inscriptions($utilisateur, [$cours->id])[$cours->id] ?? null;

        return $this->resume($cours, $inscription, $this->acces->aAcces($utilisateur->id)) + [
                'nb_chapitres' => $chapitres->count(),
                'duree_totale_minutes' => (int) $chapitres->sum('duree_estimee'),
                'xp_total' => (int) $chapitres->sum('xp_recompense'),
                'a_un_projet_guide' => $cours->projetsGuides()->exists(),
                'modules' => $modules->map(fn (Module $m) => [
                    'id' => $m->id,
                    'titre' => $m->titre,
                    'description' => $m->description,
                    'ordre' => (int) $m->ordre,
                    'chapitres' => $m->chapitres->map(fn (Chapitre $c) => [
                        'id' => $c->id,
                        'titre' => $c->titre,
                        'objectif' => $c->objectif,
                        'duree_estimee' => $c->duree_estimee,
                        'xp_recompense' => $c->xp_recompense,
                        'a_un_quiz' => (bool) $c->a_un_quiz,
                    ])->values()->all(),
                ])->values()->all(),
            ];
    }

    /**
     * Cours visibles au catalogue :
     * - tous les cours PUBLIE ;
     * - pour un apprenant déjà inscrit : son cours déjà publié en revalidation (EN_ATTENTE_VALIDATION)
     *   ou dont la modification a été refusée (REFUSE). Un cours ARCHIVE n'est jamais au catalogue.
     */
    private function visiblesAuCatalogue(Builder $q, int $apprenantId): void
    {
        $q->whereHas('statut', fn ($s) => $s->where('value', 'PUBLIE'))
            ->orWhere(function (Builder $mise) use ($apprenantId) {
                $mise->whereNotNull('date_publication')
                    ->whereHas('statut', fn ($s) => $s->whereIn('value', ['EN_ATTENTE_VALIDATION', 'REFUSE']))
                    ->whereHas('inscriptions', fn ($i) => $i->where('apprenant_id', $apprenantId));
            });
    }

    /** @return array<int, InscriptionCours> indexées par cours_id */
    private function inscriptions(Utilisateur $utilisateur, array $coursIds): array
    {
        return InscriptionCours::with('statut')
            ->where('apprenant_id', $utilisateur->id)->whereIn('cours_id', $coursIds)
            ->get()->keyBy('cours_id')->all();
    }

    private function resume(Cours $c, ?InscriptionCours $inscription, bool $acces): array
    {
        $u = $c->auteur?->utilisateur;
        $statut = $c->statut?->value;

        return [
            'id' => $c->id,
            'titre' => $c->titre,
            'description' => $c->description,
            'niveau' => $c->niveau?->value,
            'langue' => $c->langue,
            'est_certifiant' => (bool) $c->est_certifiant,
            'image_couverture' => $c->image_couverture,
            'date_publication' => $c->date_publication?->toDateString(),
            'statut' => $statut,
            // Cours déjà publié en cours de revalidation : visible par ses inscrits seulement.
            'mise_a_jour_en_cours' => $statut !== 'PUBLIE' && $c->date_publication !== null,
            // Sans abonnement qui donne accès : fiche visible, chapitres verrouillés (règle 7).
            'acces_contenu' => $acces,
            'technologie' => $c->technologie
                ? ['id' => $c->technologie->id, 'nom' => $c->technologie->nom, 'icone' => $c->technologie->icone]
                : null,
            'auteur' => $u ? trim($u->prenom.' '.$u->nom) : null,
            'inscription' => $inscription ? [
                'id' => $inscription->id,
                'statut' => $inscription->statut?->value,
                'progression' => (float) $inscription->progression,
            ] : null,
        ];
    }
}
