<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Models\MessageForum;
use App\Models\Signalement;
use App\Models\SujetDiscussion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultation du forum par l'administrateur : messages masqués compris. */
class ForumAdminController extends Controller
{
    /**
     * Sujets : ?recherche=…&technologie_id=…&est_resolu=0|1&avec_signalements=1
     */
    public function sujets(Request $request): JsonResponse
    {
        $f = $request->validate([
            'recherche'         => ['nullable', 'string', 'max:100'],
            'technologie_id'    => ['nullable', 'integer'],
            'est_resolu'        => ['nullable', 'boolean'],
            'avec_signalements' => ['nullable', 'boolean'],
            'par_page'          => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $recherche = isset($f['recherche']) ? '%' . addcslashes(trim($f['recherche']), '%_\\') . '%' : null;

        $page = SujetDiscussion::with(['auteur.role', 'technologie'])
            ->withCount([
                'messages as nb_messages',
                'messages as nb_masques' => fn ($q) => $q->where('est_masque', true),
                'messages as nb_signales' => fn ($q) => $q->where('nb_signalements', '>', 0)->where('est_masque', false),
            ])
            ->when($recherche, fn ($q, $v) => $q->where(fn ($w) => $w->where('titre', 'ilike', $v)->orWhere('contenu', 'ilike', $v)))
            ->when($f['technologie_id'] ?? null, fn ($q, $v) => $q->where('technologie_id', $v))
            ->when(isset($f['est_resolu']), fn ($q) => $q->where('est_resolu', (bool) $f['est_resolu']))
            ->when(!empty($f['avec_signalements']), fn ($q) => $q->whereHas('messages', fn ($m) => $m->where('nb_signalements', '>', 0)->where('est_masque', false)))
            ->orderByDesc('date_creation')
            ->orderByDesc('id')
            ->paginate((int) ($f['par_page'] ?? 15))
            ->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn ($s) => $this->sujet($s, false))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Un sujet et TOUS ses messages (masqués compris), avec leurs signalements en attente.
     */
    public function sujetDetail(SujetDiscussion $sujet): JsonResponse
    {
        $sujet->load(['auteur.role', 'technologie'])->loadCount([
            'messages as nb_messages',
            'messages as nb_masques' => fn ($q) => $q->where('est_masque', true),
        ]);

        $tableMessages = (new MessageForum())->getTable();
        $tableSignalements = (new Signalement())->getTable();

        $messages = MessageForum::query()
            ->select("{$tableMessages}.*")
            ->selectSub(
                Signalement::query()
                    ->selectRaw('count(*)')
                    ->whereColumn("{$tableSignalements}.message_id", "{$tableMessages}.id")
                    ->where('traite', false),
                'signalements_en_attente'
            )
            ->with(['auteur.role', 'masquePar.utilisateur'])
            ->where('sujet_id', $sujet->id)
            ->orderBy('date_envoi')
            ->orderBy('id')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => $this->sujet($sujet, true),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'contenu' => $m->contenu,
                'date_envoi' => $m->date_envoi?->format('Y-m-d H:i:s'),
                'nb_votes' => (int) $m->nb_votes,
                'nb_signalements' => (int) $m->nb_signalements,
                'signalements_en_attente' => (int) $m->signalements_en_attente,
                'est_masque' => (bool) $m->est_masque,
                'date_masquage' => $m->date_masquage?->format('Y-m-d H:i:s'),
                'masque_par' => $m->masquePar?->utilisateur
                    ? trim($m->masquePar->utilisateur->prenom . ' ' . $m->masquePar->utilisateur->nom)
                    : null,
                'auteur' => $this->personne($m->auteur),
            ])->values(),
        ]);
    }

    private function sujet(SujetDiscussion $s, bool $complet): array
    {
        return [
            'id' => $s->id,
            'titre' => $s->titre,
            'contenu' => $complet ? $s->contenu : mb_strimwidth((string) $s->contenu, 0, 180, '…'),
            'est_resolu' => (bool) $s->est_resolu,
            'date_creation' => $s->date_creation?->format('Y-m-d H:i:s'),
            'auteur' => $this->personne($s->auteur),
            'technologie' => $s->technologie ? ['id' => $s->technologie->id, 'nom' => $s->technologie->nom] : null,
            'nb_messages' => (int) ($s->nb_messages ?? 0),
            'nb_masques' => (int) ($s->nb_masques ?? 0),
            'nb_signales' => (int) ($s->nb_signales ?? 0),
        ];
    }

    private function personne($u): ?array
    {
        return $u ? [
            'id' => $u->id,
            'nom' => $u->nom,
            'prenom' => $u->prenom,
            'role' => $u->role?->value,
        ] : null;
    }
}
