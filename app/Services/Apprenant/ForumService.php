<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\MessageForum;
use App\Models\Signalement;
use App\Models\SujetDiscussion;
use App\Models\Utilisateur;
use App\Models\Vote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Forum par technologie. Réservé aux abonnés (middleware acces.forum) ; encadreurs et administrateurs y ont accès.
 * La modération (traitement des signalements, masquage) relève de l'administrateur : non incluse ici.
 */
class ForumService
{
    /** Compteurs d'un sujet, calculés sur ses messages visibles uniquement. */
    public static function compteurs(): array
    {
        return [
            'messages as nb_messages' => fn ($q) => $q->where('est_masque', false),
        ];
    }

    /** @param array{technologie_id?: int, est_resolu?: bool|null, recherche?: string, mes_sujets?: bool} $filtres */
    public function sujets(array $filtres, Utilisateur $moi, int $parPage = 15): LengthAwarePaginator
    {
        $recherche = isset($filtres['recherche']) ? addcslashes($filtres['recherche'], '%_\\') : null;

        return SujetDiscussion::with(['auteur.role', 'technologie'])
            ->withCount(self::compteurs())
            ->withSum(['messages as nb_votes' => fn ($q) => $q->where('est_masque', false)], 'nb_votes')
            ->when($filtres['technologie_id'] ?? null, fn ($q, $v) => $q->where('technologie_id', $v))
            ->when(isset($filtres['est_resolu']), fn ($q) => $q->where('est_resolu', (bool) $filtres['est_resolu']))
            ->when($recherche, fn ($q, $v) => $q->where(fn ($w) => $w->where('titre', 'ilike', "%{$v}%")->orWhere('contenu', 'ilike', "%{$v}%")))
            ->when(! empty($filtres['mes_sujets']), fn ($q) => $q->where('auteur_id', $moi->id))
            ->orderByDesc('date_creation')
            ->orderByDesc('id')
            ->paginate(max(1, min($parPage, 50)));
    }

    /** Charge les compteurs (réponses et votes visibles) sur un sujet déjà récupéré. */
    public function chargerCompteurs(SujetDiscussion $sujet): SujetDiscussion
    {
        return $sujet->loadCount(self::compteurs())
            ->loadSum(['messages as nb_votes' => fn ($q) => $q->where('est_masque', false)], 'nb_votes');
    }

    public function creerSujet(Utilisateur $auteur, array $donnees): SujetDiscussion
    {
        $sujet = SujetDiscussion::create([
            'titre' => $donnees['titre'],
            'contenu' => $donnees['contenu'],
            'date_creation' => now(),
            'est_resolu' => false,
            'auteur_id' => $auteur->id,
            'technologie_id' => $donnees['technologie_id'],
        ]);

        $sujet->setAttribute('nb_messages', 0);
        $sujet->setAttribute('nb_votes', 0);

        return $sujet->load('auteur.role', 'technologie');
    }

    /** Un sujet avec ses messages visibles (les messages masqués n'apparaissent plus), du plus ancien au plus récent. */
    public function messages(SujetDiscussion $sujet, int $parPage = 20): LengthAwarePaginator
    {
        return MessageForum::with('auteur.role')
            ->where('sujet_id', $sujet->id)
            ->where('est_masque', false)
            ->orderBy('date_envoi')
            ->orderBy('id')
            ->paginate(max(1, min($parPage, 50)));
    }

    /** @return list<int> ids des messages (parmi $ids) déjà approuvés par l'utilisateur */
    public function votesDe(Utilisateur $utilisateur, array $ids): array
    {
        return Vote::where('utilisateur_id', $utilisateur->id)->whereIn('message_id', $ids)->pluck('message_id')->map(fn ($i) => (int) $i)->all();
    }

    public function repondre(Utilisateur $auteur, SujetDiscussion $sujet, string $contenu): MessageForum
    {
        $message = MessageForum::create([
            'contenu' => $contenu,
            'date_envoi' => now(),
            'nb_votes' => 0,
            'nb_signalements' => 0,
            'est_masque' => false,
            'sujet_id' => $sujet->id,
            'auteur_id' => $auteur->id,
        ]);

        return $message->load('auteur.role');
    }

    /** MessageForum.voter() : une seule fois par utilisateur, pas sur son propre message ; nbVotes recalculé. */
    public function voter(Utilisateur $utilisateur, MessageForum $message): MessageForum
    {
        return DB::transaction(function () use ($utilisateur, $message) {
            $message = $this->messageVisibleVerrouille($message);

            if ((int) $message->auteur_id === (int) $utilisateur->id) {
                throw new RegleMetierException('Vous ne pouvez pas approuver votre propre message.', 422);
            }

            if (Vote::where('utilisateur_id', $utilisateur->id)->where('message_id', $message->id)->exists()) {
                throw new RegleMetierException('Vous avez déjà approuvé ce message.', 409);
            }

            Vote::create(['utilisateur_id' => $utilisateur->id, 'message_id' => $message->id, 'date_vote' => now()]);
            $message->update(['nb_votes' => Vote::where('message_id', $message->id)->count()]);

            return $message->fresh('auteur.role');
        });
    }

    /** MessageForum.retirerVote() : supprime le vote de l'utilisateur (sans effet s'il n'avait pas voté) ; nbVotes recalculé. */
    public function retirerVote(Utilisateur $utilisateur, MessageForum $message): MessageForum
    {
        return DB::transaction(function () use ($utilisateur, $message) {
            $message = $this->messageVisibleVerrouille($message);

            Vote::where('utilisateur_id', $utilisateur->id)->where('message_id', $message->id)->delete();
            $message->update(['nb_votes' => Vote::where('message_id', $message->id)->count()]);

            return $message->fresh('auteur.role');
        });
    }

    /** MessageForum.signaler(motif) : une seule fois par utilisateur, pas sur son propre message ; nbSignalements recalculé. */
    public function signaler(Utilisateur $utilisateur, MessageForum $message, string $motif): void
    {
        DB::transaction(function () use ($utilisateur, $message, $motif) {
            $message = $this->messageVisibleVerrouille($message);

            if ((int) $message->auteur_id === (int) $utilisateur->id) {
                throw new RegleMetierException('Vous ne pouvez pas signaler votre propre message.', 422);
            }

            if (Signalement::where('utilisateur_id', $utilisateur->id)->where('message_id', $message->id)->exists()) {
                throw new RegleMetierException('Vous avez déjà signalé ce message.', 409);
            }

            Signalement::create([
                'motif' => $motif,
                'date_signalement' => now(),
                'traite' => false,
                'utilisateur_id' => $utilisateur->id,
                'message_id' => $message->id,
            ]);

            $message->update(['nb_signalements' => Signalement::where('message_id', $message->id)->count()]);
        });
    }

    /** SujetDiscussion.marquerResolu() : réservé à l'auteur du sujet ou à un administrateur (idempotent). */
    public function marquerResolu(Utilisateur $utilisateur, SujetDiscussion $sujet): SujetDiscussion
    {
        if ((int) $sujet->auteur_id !== (int) $utilisateur->id && ! $utilisateur->aLeRole('ADMIN')) {
            throw new RegleMetierException('Seul l’auteur du sujet ou un administrateur peut le marquer comme résolu.', 403);
        }

        if (! $sujet->est_resolu) {
            $sujet->update(['est_resolu' => true]);
        }

        return $this->chargerCompteurs($sujet->load('auteur.role', 'technologie'));
    }

    /** Verrouille le message pour sérialiser les votes concurrents ; un message masqué est introuvable. */
    private function messageVisibleVerrouille(MessageForum $message): MessageForum
    {
        $message = MessageForum::whereKey($message->id)->lockForUpdate()->firstOrFail();

        if ($message->est_masque) {
            throw new RegleMetierException('Message introuvable.', 404);
        }

        return $message;
    }
}
