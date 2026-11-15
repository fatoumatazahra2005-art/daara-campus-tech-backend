<?php

namespace App\Http\Resources\Apprenant;

use App\Models\MessageForum;
use App\Models\SujetDiscussion;
use App\Models\Utilisateur;

/** Format des réponses du forum. Les signalements ne sont jamais exposés aux utilisateurs ordinaires. */
class PresenteurForum
{
    public static function auteur(?Utilisateur $u): ?array
    {
        return $u ? [
            'id' => $u->id,
            'nom' => trim($u->prenom.' '.$u->nom),
            'role' => $u->role?->value,
        ] : null;
    }

    public static function sujet(SujetDiscussion $s, Utilisateur $moi, bool $complet = false): array
    {
        return [
            'id' => $s->id,
            'titre' => $s->titre,
            'contenu' => $complet ? $s->contenu : mb_strimwidth((string) $s->contenu, 0, 160, '…'),
            'date_creation' => $s->date_creation?->toIso8601String(),
            'est_resolu' => (bool) $s->est_resolu,
            'technologie' => $s->technologie ? ['id' => $s->technologie->id, 'nom' => $s->technologie->nom] : null,
            'auteur' => self::auteur($s->auteur),
            'nb_messages' => (int) ($s->nb_messages ?? 0),
            'nb_votes' => (int) ($s->nb_votes ?? 0),
            'est_mon_sujet' => (int) $s->auteur_id === (int) $moi->id,
        ];
    }

    /** @param list<int> $votesMoi ids des messages déjà approuvés par l'utilisateur */
    public static function message(MessageForum $m, Utilisateur $moi, array $votesMoi): array
    {
        return [
            'id' => $m->id,
            'contenu' => $m->contenu,
            'date_envoi' => $m->date_envoi?->toIso8601String(),
            'nb_votes' => (int) $m->nb_votes,
            'a_vote' => in_array((int) $m->id, $votesMoi, true),
            'auteur' => self::auteur($m->auteur),
            'est_mon_message' => (int) $m->auteur_id === (int) $moi->id,
        ];
    }
}
