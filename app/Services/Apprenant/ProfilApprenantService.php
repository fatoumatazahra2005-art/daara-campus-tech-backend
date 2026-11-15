<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Apprenant;
use App\Models\Certificat;
use App\Models\CodeList;
use App\Models\InscriptionCours;
use App\Models\InscriptionParcours;
use App\Models\Paiement;
use App\Models\RenduProjet;
use App\Models\TentativeQuiz;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Profil de l'apprenant : consultation, modification, mot de passe, suppression (anonymisation). */
class ProfilApprenantService
{
    public function profil(Utilisateur $u): array
    {
        $a = Apprenant::with('niveau')->findOrFail($u->id);

        $palier = ProgressionService::PALIER_XP;
        $xp = (int) $a->xp_total;
        $parStatut = fn (string $v) => InscriptionCours::where('apprenant_id', $u->id)
            ->whereHas('statut', fn ($q) => $q->where('value', $v))->count();

        return [
            'id' => $u->id,
            'prenom' => $u->prenom,
            'nom' => $u->nom,
            'email' => $u->email,
            'telephone' => $u->telephone,
            'niveau' => $a->niveau?->value,
            'niveaux_disponibles' => $this->niveaux(),
            'xp_total' => $xp,
            'niveau_xp' => (int) $a->niveau_xp,
            'palier_xp' => $palier,
            'xp_pour_prochain_niveau' => $palier - ($xp % $palier),
            'serie_jours_consecutifs' => (int) $a->serie_jours_consecutifs,
            'date_derniere_activite' => $a->date_derniere_activite?->toDateString(),
            'membre_depuis' => $u->created_at?->toDateString(),
            'statistiques' => [
                'cours_en_cours' => $parStatut('EN_COURS'),
                'cours_termines' => $parStatut('TERMINEE'),
                'certificats' => Certificat::whereHas('inscriptionCours', fn ($q) => $q->where('apprenant_id', $u->id))->count(),
                'parcours_termines' => InscriptionParcours::where('apprenant_id', $u->id)
                    ->whereHas('statut', fn ($q) => $q->where('value', 'TERMINEE'))->count(),
                'projets_valides' => RenduProjet::where('apprenant_id', $u->id)
                    ->whereHas('statut', fn ($q) => $q->where('value', 'CORRIGE'))->count(),
                'quiz_reussis' => TentativeQuiz::where('apprenant_id', $u->id)
                    ->whereHas('statut', fn ($q) => $q->where('value', 'REUSSI'))
                    ->distinct()->count('quiz_id'),
            ],
        ];
    }

    /**
     * Utilisateur.modifierProfil() : prénom, nom, email, téléphone, niveau.
     * Changer l'email exige le mot de passe actuel.
     */
    public function modifier(Utilisateur $u, array $d): array
    {
        $nouvelEmail = isset($d['email']) ? mb_strtolower(trim($d['email'])) : null;
        $emailChange = $nouvelEmail !== null && $nouvelEmail !== mb_strtolower((string) $u->email);

        if ($emailChange) {
            if (empty($d['mot_de_passe_actuel'])) {
                throw new RegleMetierException('Saisissez votre mot de passe pour changer d’adresse e-mail.', 422);
            }
            $this->verifierMotDePasse($u, $d['mot_de_passe_actuel']);

            $pris = Utilisateur::whereRaw('LOWER(TRIM(email)) = ?', [$nouvelEmail])->where('id', '!=', $u->id)->exists();
            if ($pris) {
                throw new RegleMetierException('Cette adresse e-mail est déjà utilisée.', 422);
            }
        }

        DB::transaction(function () use ($u, $d, $emailChange, $nouvelEmail) {
            $u->update(array_filter([
                    'prenom' => isset($d['prenom']) ? trim($d['prenom']) : null,
                    'nom' => isset($d['nom']) ? trim($d['nom']) : null,
                    'email' => $emailChange ? $nouvelEmail : null,
                ], fn ($v) => $v !== null) + (array_key_exists('telephone', $d) ? ['telephone' => $this->telephone($d['telephone'])] : []));

            if (! empty($d['niveau'])) {
                $type = $this->typeNiveau();
                $id = $type ? CodeList::where('type', $type)->where('value', $d['niveau'])->value('id') : null;
                if (! $id) {
                    throw new RegleMetierException('Niveau inconnu.', 422);
                }
                Apprenant::whereKey($u->id)->update(['niveau_id' => $id]);
            }
        });

        return $this->profil($u->fresh());
    }

    /** Change le mot de passe ; les autres sessions sont déconnectées. */
    public function changerMotDePasse(Utilisateur $u, string $actuel, string $nouveau, ?int $jetonCourantId): void
    {
        $this->verifierMotDePasse($u, $actuel);

        if (Hash::check($nouveau, $u->mot_de_passe_hash)) {
            throw new RegleMetierException('Le nouveau mot de passe doit être différent de l’actuel.', 422);
        }

        DB::transaction(function () use ($u, $nouveau, $jetonCourantId) {
            $u->update(['mot_de_passe_hash' => Hash::make($nouveau)]);
            $u->tokens()->when($jetonCourantId, fn ($q) => $q->where('id', '!=', $jetonCourantId))->delete();
        });
    }

    /**
     * Suppression demandée par l'apprenant : compte DESACTIVE, identité anonymisée,
     * sessions fermées, demandes de paiement en attente annulées.
     * Paiements, certificats (nom figé), progression et messages du forum sont conservés.
     */
    public function supprimerCompte(Utilisateur $u, string $motDePasse): void
    {
        $this->verifierMotDePasse($u, $motDePasse);

        DB::transaction(function () use ($u) {
            Paiement::whereHas('periode.abonnement', fn ($q) => $q->where('apprenant_id', $u->id))
                ->whereHas('statut', fn ($q) => $q->where('value', 'EN_ATTENTE'))
                ->update(['statut_id' => CodeList::idOf('StatutPaiement', 'ECHOUE')]);

            $u->update([
                'prenom' => 'Utilisateur',
                'nom' => 'supprimé',
                'email' => 'supprime-'.$u->id.'-'.Str::lower(Str::random(6)).'@anonyme.local',
                'telephone' => null,
                'mot_de_passe_hash' => Hash::make(Str::random(40)),
                'statut_compte_id' => CodeList::idOf('StatutCompte', 'DESACTIVE'),
            ]);

            $u->tokens()->delete();
        });
    }

    private function verifierMotDePasse(Utilisateur $u, string $motDePasse): void
    {
        if (! Hash::check($motDePasse, $u->mot_de_passe_hash)) {
            throw new RegleMetierException('Mot de passe incorrect.', 422);
        }
    }

    /** Type de CodeList des niveaux (celui qui contient DEBUTANT). */
    private function typeNiveau(): ?string
    {
        return CodeList::where('value', 'DEBUTANT')->value('type');
    }

    /** @return list<string> */
    private function niveaux(): array
    {
        $type = $this->typeNiveau();

        return $type ? CodeList::where('type', $type)->orderBy('id')->pluck('value')->all() : [];
    }

    private function telephone(?string $t): ?string
    {
        $t = preg_replace('/[\s.\-]/', '', (string) $t);

        return $t === '' ? null : $t;
    }
}
