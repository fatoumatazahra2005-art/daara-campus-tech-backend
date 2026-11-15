<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\PresenteurForum;
use App\Models\MessageForum;
use App\Models\SujetDiscussion;
use App\Models\Technologie;
use App\Services\Apprenant\ForumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

/** Forum : réservé aux abonnés (middleware acces.forum), ouvert aux encadreurs et administrateurs. */
class ForumController extends Controller
{
    public function __construct(private ForumService $forum)
    {
    }

    public function sujets(Request $request): JsonResponse
    {
        $f = $request->validate([
            'technologie_id' => ['nullable', 'integer'],
            'est_resolu' => ['nullable', 'boolean'],
            'recherche' => ['nullable', 'string', 'max:100'],
            'mes_sujets' => ['nullable', 'boolean'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $moi = $request->user();
        $page = $this->forum->sujets($f, $moi, (int) ($f['par_page'] ?? 15))
            ->through(fn ($s) => PresenteurForum::sujet($s, $moi));

        return response()->json(JsonResource::collection($page)->response()->getData(true));
    }

    public function creerSujet(Request $request): JsonResponse
    {
        $d = $request->validate([
            'titre' => ['required', 'string', 'max:200'],
            'contenu' => ['required', 'string', 'max:10000'],
            'technologie_id' => ['required', 'integer', Rule::exists(Technologie::class, 'id')],
        ]);

        $sujet = $this->forum->creerSujet($request->user(), $d);

        return response()->json(['data' => PresenteurForum::sujet($sujet, $request->user(), true)], 201);
    }

    /** Le sujet et la page demandée de ses messages visibles. */
    public function sujet(Request $request, SujetDiscussion $sujet): JsonResponse
    {
        $moi = $request->user();
        $this->forum->chargerCompteurs($sujet->load('auteur.role', 'technologie'));

        $page = $this->forum->messages($sujet, (int) $request->query('par_page', 20));
        $votes = $this->forum->votesDe($moi, $page->pluck('id')->all());
        $page->through(fn ($m) => PresenteurForum::message($m, $moi, $votes));

        return response()->json([
            'data' => PresenteurForum::sujet($sujet, $moi, true),
            'messages' => JsonResource::collection($page)->response()->getData(true),
        ]);
    }

    public function repondre(Request $request, SujetDiscussion $sujet): JsonResponse
    {
        $d = $request->validate(['contenu' => ['required', 'string', 'max:5000']]);

        $message = $this->forum->repondre($request->user(), $sujet, $d['contenu']);

        return response()->json(['data' => PresenteurForum::message($message, $request->user(), [])], 201);
    }

    public function resoudre(Request $request, SujetDiscussion $sujet): JsonResponse
    {
        return response()->json([
            'data' => PresenteurForum::sujet($this->forum->marquerResolu($request->user(), $sujet), $request->user(), true),
        ]);
    }

    public function voter(Request $request, MessageForum $message): JsonResponse
    {
        $m = $this->forum->voter($request->user(), $message);

        return response()->json(['data' => PresenteurForum::message($m, $request->user(), [$m->id])], 201);
    }

    public function retirerVote(Request $request, MessageForum $message): JsonResponse
    {
        $m = $this->forum->retirerVote($request->user(), $message);

        return response()->json(['data' => PresenteurForum::message($m, $request->user(), [])]);
    }

    public function signaler(Request $request, MessageForum $message): JsonResponse
    {
        $d = $request->validate(['motif' => ['required', 'string', 'max:255']]);

        $this->forum->signaler($request->user(), $message, $d['motif']);

        return response()->json(['message' => 'Merci, le message a été signalé aux modérateurs.'], 201);
    }
}
