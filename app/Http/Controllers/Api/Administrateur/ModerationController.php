<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Models\Administrateur;
use App\Http\Resources\SignalementResource;
use App\Http\Resources\MessageForumResource;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModerationController extends Controller
{
    public function __construct(
        private ModerationService $moderationService
    ) {}

    /**
     * Liste des signalements.
     */
    public function signalements(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'traite' => ['nullable', 'boolean'],
            'recherche' => ['nullable', 'string', 'max:255'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $signalements = $this->moderationService
            ->listerSignalements($filtres);

        return SignalementResource::collection($signalements);
    }

    /**
     * Détails d'un signalement.
     */
    public function detailsSignalement(int $id): JsonResponse
    {
        $signalement = $this->moderationService
            ->detailsSignalement($id);

        return response()->json([
            'message' => 'Signalement récupéré avec succès.',
            'data' => new SignalementResource($signalement),
        ]);
    }

    /**
     * Marquer un signalement comme traité, au nom de l'administrateur connecté (traitePar).
     */
    public function traiterSignalement(Request $request, int $id): JsonResponse
    {
        $administrateur = Administrateur::findOrFail(
            $request->user()->id
        );

        $signalement = $this->moderationService->traiterSignalement(
            $id,
            $administrateur->id
        );

        return response()->json([
            'message' => 'Signalement marqué comme traité.',
            'data' => new SignalementResource($signalement),
        ]);
    }

    /**
     * Masquer un message (ses signalements en attente sont traités par le même administrateur).
     */
    public function masquerMessage(
        Request $request,
        int $messageId
    ): JsonResponse {
        $administrateur = Administrateur::findOrFail(
            $request->user()->id
        );

        $message = $this->moderationService->masquerMessage(
            $messageId,
            $administrateur->id
        );

        return response()->json([
            'message' => 'Message masqué avec succès.',
            'data' => new MessageForumResource($message),
        ]);
    }

    /**
     * Liste des messages masqués.
     */
    public function messagesMasques(Request $request): AnonymousResourceCollection
    {
        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:255'],
            'par_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $messages = $this->moderationService
            ->listerMessagesMasques($filtres);

        return MessageForumResource::collection($messages);
    }

    /**
     * Rétablir un message masqué.
     */
    public function demasquerMessage(int $messageId): JsonResponse
    {
        $message = $this->moderationService->demasquerMessage($messageId);

        return response()->json([
            'message' => 'Message rétabli.',
            'data' => new MessageForumResource($message),
        ]);
    }
}
