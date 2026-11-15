<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Administrateur;
use App\Models\MessageForum;
use App\Models\Signalement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Modération du forum : signalements et masquage de messages.
 * Un signalement traité garde la trace de l'administrateur qui l'a traité (traitePar).
 */
class ModerationService
{
    private const RELATIONS_SIGNALEMENT = [
        'utilisateur',
        'message.auteur',
        'message.sujet',
        'traitePar.utilisateur',
    ];

    private const RELATIONS_MESSAGE = [
        'auteur',
        'sujet',
        'masquePar.utilisateur',
    ];

    // =====================================================================
    // Signalements
    // =====================================================================

    /**
     * Lister les signalements : ?traite=0|1&recherche=…
     */
    public function listerSignalements(array $filtres = []): LengthAwarePaginator
    {
        $query = Signalement::with(self::RELATIONS_SIGNALEMENT);

        if (isset($filtres['traite'])) {
            $query->where('traite', (bool) $filtres['traite']);
        }

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $query->where(function ($q) use ($recherche) {
                $q->where('motif', 'ilike', $recherche)
                    ->orWhereHas('message', fn ($m) => $m->where('contenu', 'ilike', $recherche))
                    ->orWhereHas('utilisateur', function ($u) use ($recherche) {
                        $u->where('nom', 'ilike', $recherche)
                            ->orWhere('prenom', 'ilike', $recherche)
                            ->orWhere('email', 'ilike', $recherche);
                    });
            });
        }

        return $query->latest('date_signalement')
            ->paginate($filtres['par_page'] ?? 10)
            ->withQueryString();
    }

    public function detailsSignalement(int $id): Signalement
    {
        $signalement = Signalement::with(self::RELATIONS_SIGNALEMENT)->find($id);

        if (!$signalement) {
            throw new RegleMetierException('Signalement introuvable.', 404);
        }

        return $signalement;
    }

    /**
     * Clore un signalement sans masquer le message (signalement jugé non fondé).
     * L'administrateur qui le traite est enregistré dans traite_par_id.
     */
    public function traiterSignalement(int $id, int $administrateurId): Signalement
    {
        $signalement = $this->detailsSignalement($id);

        if (!$signalement->traite) {
            $administrateur = Administrateur::findOrFail($administrateurId);

            $signalement->update([
                'traite' => true,
                'traite_par_id' => $administrateur->id,
            ]);
        }

        return $signalement->refresh()->load(self::RELATIONS_SIGNALEMENT);
    }

    // =====================================================================
    // Messages
    // =====================================================================

    /**
     * Masquer un message et clore tous ses signalements en attente,
     * au nom de l'administrateur qui masque.
     */
    public function masquerMessage(int $messageId, int $administrateurId): MessageForum
    {
        return DB::transaction(function () use ($messageId, $administrateurId) {

            $message = MessageForum::whereKey($messageId)->lockForUpdate()->first();

            if (!$message) {
                throw new RegleMetierException('Message introuvable.', 404);
            }

            $administrateur = Administrateur::findOrFail($administrateurId);

            if (!$message->est_masque) {
                $message->update([
                    'est_masque' => true,
                    'date_masquage' => now(),
                    'masque_par_id' => $administrateur->id,
                ]);
            }

            // Le message étant masqué, ses signalements sont résolus par cet administrateur.
            Signalement::where('message_id', $message->id)
                ->where('traite', false)
                ->update([
                    'traite' => true,
                    'traite_par_id' => $administrateur->id,
                    'updated_at' => now(),
                ]);

            return $message->refresh()->load(self::RELATIONS_MESSAGE);
        });
    }

    /**
     * Rétablir un message masqué par erreur.
     * Les signalements restent traités : ils gardent la trace de la décision prise.
     */
    public function demasquerMessage(int $messageId): MessageForum
    {
        $message = MessageForum::find($messageId);

        if (!$message) {
            throw new RegleMetierException('Message introuvable.', 404);
        }

        if (!$message->est_masque) {
            throw new RegleMetierException('Ce message n\'est pas masqué.');
        }

        $message->update([
            'est_masque' => false,
            'date_masquage' => null,
            'masque_par_id' => null,
        ]);

        return $message->refresh()->load(['auteur', 'sujet']);
    }

    /**
     * Lister les messages masqués : ?recherche=…
     */
    public function listerMessagesMasques(array $filtres = []): LengthAwarePaginator
    {
        $query = MessageForum::with(self::RELATIONS_MESSAGE)->where('est_masque', true);

        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';
            $query->where('contenu', 'ilike', $recherche);
        }

        return $query->latest('date_masquage')
            ->paginate($filtres['par_page'] ?? 10)
            ->withQueryString();
    }
}
