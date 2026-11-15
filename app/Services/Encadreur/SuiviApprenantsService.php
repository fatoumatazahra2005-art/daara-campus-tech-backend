<?php

namespace App\Services\Encadreur;

use App\Exceptions\RegleMetierException;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\ProgressionChapitre;
use App\Models\Quiz;
use App\Models\TentativeQuiz;
use App\Models\Utilisateur;
use App\Services\Apprenant\AccesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Suivi, par l'encadreur, des apprenants inscrits à SES cours. */
class SuiviApprenantsService
{
    /** Un apprenant EN_COURS sans activité depuis ce nombre de jours est signalé « inactif ». */
    public const JOURS_INACTIVITE = 14;

    public function __construct(private AccesService $acces)
    {
    }

    /** Ids des cours dont l'encadreur est l'auteur. */
    private function mesCours(Utilisateur $encadreur): Collection
    {
        return Cours::where('auteur_id', $encadreur->id)->pluck('id');
    }

    /** Résumé par cours : inscrits, en cours, terminés, abandonnés, progression moyenne. */
    public function resume(Utilisateur $encadreur): array
    {
        $cours = Cours::with('statut')->where('auteur_id', $encadreur->id)->orderBy('titre')->get(['id', 'titre', 'statut_id']);

        $inscriptions = InscriptionCours::with('statut')
            ->whereIn('cours_id', $cours->pluck('id'))
            ->get(['id', 'cours_id', 'statut_id', 'progression'])
            ->groupBy('cours_id');

        return $cours->map(function (Cours $c) use ($inscriptions) {
            $liste = $inscriptions->get($c->id, collect());
            $parStatut = fn (string $v) => $liste->filter(fn ($i) => $i->statut?->value === $v)->count();

            return [
                'id' => $c->id,
                'titre' => $c->titre,
                'statut' => $c->statut?->value,
                'inscrits' => $liste->count(),
                'en_cours' => $parStatut('EN_COURS'),
                'termines' => $parStatut('TERMINEE'),
                'abandons' => $parStatut('ABANDONNEE'),
                'progression_moyenne' => $liste->isEmpty() ? 0 : round($liste->avg(fn ($i) => (float) $i->progression), 1),
            ];
        })->values()->all();
    }

    /** Liste paginée des inscriptions aux cours de l'encadreur. */
    public function lister(Utilisateur $encadreur, array $f): array
    {
        $t = (new InscriptionCours)->getTable();
        $tp = (new ProgressionChapitre)->getTable();
        $mesCours = $this->mesCours($encadreur);

        $idsApprenants = null;
        if (! empty($f['recherche'])) {
            $r = '%'.addcslashes(trim($f['recherche']), '%_\\').'%';
            $idsApprenants = Utilisateur::where(fn ($q) => $q->where('nom', 'ilike', $r)
                ->orWhere('prenom', 'ilike', $r)
                ->orWhere('email', 'ilike', $r))->pluck('id');
        }

        $page = InscriptionCours::query()
            ->select("$t.*")
            ->addSelect([
                'derniere_activite' => ProgressionChapitre::selectRaw('MAX(COALESCE(date_fin, date_debut))')
                    ->whereColumn("$tp.inscription_cours_id", "$t.id"),
                'nb_termines' => ProgressionChapitre::selectRaw('COUNT(*)')
                    ->whereColumn("$tp.inscription_cours_id", "$t.id")
                    ->where("$tp.est_termine", true),
            ])
            ->with(['statut', 'cours:id,titre'])
            ->whereIn("$t.cours_id", $mesCours)
            ->when($f['cours_id'] ?? null, fn ($q, $v) => $q->where("$t.cours_id", $v))
            ->when($f['statut'] ?? null, fn ($q, $v) => $q->whereHas('statut', fn ($s) => $s->where('value', $v)))
            ->when($idsApprenants !== null, fn ($q) => $q->whereIn("$t.apprenant_id", $idsApprenants))
            ->orderByRaw('derniere_activite DESC NULLS LAST')
            ->orderByDesc("$t.id")
            ->paginate(max(1, min((int) ($f['par_page'] ?? 15), 50)));

        $utilisateurs = Utilisateur::whereIn('id', $page->getCollection()->pluck('apprenant_id'))->get()->keyBy('id');

        // Nombre de chapitres actifs par cours (pour « 3 / 8 chapitres »).
        $totaux = $page->getCollection()->pluck('cours_id')->unique()
            ->mapWithKeys(fn ($id) => [$id => $this->acces->chapitreIdsActifs($id)->count()]);

        return [
            'data' => $page->getCollection()->map(fn (InscriptionCours $i) => $this->ligne(
                $i,
                $utilisateurs->get($i->apprenant_id),
                (int) ($totaux[$i->cours_id] ?? 0)
            ))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /** Détail d'une inscription : chapitre par chapitre + quiz. */
    public function detail(Utilisateur $encadreur, InscriptionCours $inscription): array
    {
        $inscription->loadMissing('statut', 'cours');

        if ((int) $inscription->cours?->auteur_id !== (int) $encadreur->id) {
            throw new RegleMetierException('Inscription introuvable.', 404);
        }

        $tm = (new \App\Models\Module)->getTable();
        $chapitres = $this->acces->requeteChapitresActifs($inscription->cours_id)
            ->addSelect("$tm.titre as module_titre")
            ->get();

        $progressions = ProgressionChapitre::where('inscription_cours_id', $inscription->id)
            ->get()->keyBy('chapitre_id');

        $quiz = Quiz::with('mode')->where('actif', true)->whereIn('chapitre_id', $chapitres->pluck('id'))->get()->keyBy('chapitre_id');

        $tentatives = TentativeQuiz::with('statut')
            ->where('apprenant_id', $inscription->apprenant_id)
            ->whereIn('quiz_id', $quiz->pluck('id'))
            ->get()->groupBy('quiz_id');

        $utilisateur = Utilisateur::find($inscription->apprenant_id);
        $derniere = $progressions->map(fn ($p) => $p->date_fin ?? $p->date_debut)->filter()->max();

        return $this->ligne($inscription, $utilisateur, $chapitres->count(), [
                'derniere_activite' => $derniere,
                'nb_termines' => $progressions->where('est_termine', true)->count(),
            ]) + [
                'chapitres' => $chapitres->map(function ($c) use ($progressions, $quiz, $tentatives) {
                    $p = $progressions->get($c->id);
                    $q = $quiz->get($c->id);
                    $essais = $q ? $tentatives->get($q->id, collect()) : collect();

                    return [
                        'id' => $c->id,
                        'titre' => $c->titre,
                        'module' => $c->module_titre,
                        'ouvert' => (bool) $p,
                        'termine' => (bool) $p?->est_termine,
                        'date_fin' => $p?->date_fin ? Carbon::parse($p->date_fin)->toIso8601String() : null,
                        'quiz' => $q ? [
                            'titre' => $q->titre,
                            'mode' => $q->mode?->value,
                            'seuil' => (float) $q->seuil_reussite,
                            'tentatives' => $essais->count(),
                            'meilleur_score' => $essais->whereNotNull('score')->max('score'),
                            'reussi' => $essais->contains(fn ($e) => $e->statut?->value === 'REUSSI'),
                        ] : null,
                    ];
                })->values(),
            ];
    }

    private function ligne(InscriptionCours $i, ?Utilisateur $u, int $totalChapitres, array $calcule = []): array
    {
        $derniere = $calcule['derniere_activite'] ?? $i->derniere_activite;
        $derniere = $derniere ? Carbon::parse($derniere) : null;
        $statut = $i->statut?->value;

        return [
            'id' => $i->id,
            'apprenant' => $u ? [
                'id' => $u->id,
                'nom' => trim($u->prenom.' '.$u->nom),
                'email' => $u->email,
            ] : null,
            'cours' => ['id' => $i->cours_id, 'titre' => $i->cours?->titre],
            'statut' => $statut,
            'progression' => (float) $i->progression,
            'date_inscription' => $i->date_inscription ? Carbon::parse($i->date_inscription)->toDateString() : null,
            'derniere_activite' => $derniere?->toIso8601String(),
            'nb_chapitres_termines' => (int) ($calcule['nb_termines'] ?? $i->nb_termines ?? 0),
            'nb_chapitres' => $totalChapitres,
            'inactif' => $statut === 'EN_COURS'
                && (! $derniere || $derniere->lt(now()->subDays(self::JOURS_INACTIVITE))),
        ];
    }
}
