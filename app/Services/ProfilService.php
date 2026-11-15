<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\CodeList;
use App\Models\Encadreur;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Profil de l'administrateur et de l'encadreur (espace web).
 * Champs communs (Utilisateur) : prénom, nom, email, téléphone.
 * Champ propre à l'encadreur : spécialité. L'administrateur n'a pas de champ propre.
 * Le rôle et le statut du compte ne se modifient pas ici.
 */
class ProfilService
{
    public function profil(Utilisateur $u): array
    {
        $role = CodeList::find($u->role_id)?->value;

        $donnees = [
            'id' => $u->id,
            'prenom' => $u->prenom,
            'nom' => $u->nom,
            'email' => $u->email,
            'telephone' => $u->telephone,
            'role' => $role,
            'membre_depuis' => $u->created_at?->toDateString(),
        ];

        if ($role === 'ENCADREUR') {
            $donnees['specialite'] = Encadreur::whereKey($u->id)->value('specialite');
        }

        return $donnees;
    }

    /**
     * Utilisateur.modifierProfil() : prénom, nom, téléphone, email (+ spécialité pour l'encadreur).
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

            $pris = Utilisateur::whereRaw('LOWER(TRIM(email)) = ?', [$nouvelEmail])
                ->where('id', '!=', $u->id)
                ->exists();

            if ($pris) {
                throw new RegleMetierException('Cette adresse e-mail est déjà utilisée.', 422);
            }
        }

        $telephone = array_key_exists('telephone', $d) ? $this->telephone($d['telephone']) : $u->telephone;

        if ($telephone && Utilisateur::where('telephone', $telephone)->where('id', '!=', $u->id)->exists()) {
            throw new RegleMetierException('Ce numéro de téléphone est déjà utilisé.', 422);
        }

        DB::transaction(function () use ($u, $d, $emailChange, $nouvelEmail, $telephone) {
            $u->prenom = isset($d['prenom']) ? trim($d['prenom']) : $u->prenom;
            $u->nom = isset($d['nom']) ? trim($d['nom']) : $u->nom;
            $u->telephone = $telephone;

            if ($emailChange) {
                $u->email = $nouvelEmail;
            }

            $u->save();

            // Spécialité : seulement pour un encadreur.
            if (array_key_exists('specialite', $d) && CodeList::find($u->role_id)?->value === 'ENCADREUR') {
                $specialite = trim((string) $d['specialite']);

                Encadreur::whereKey($u->id)->update(['specialite' => $specialite !== '' ? $specialite : null]);
            }
        });

        return $this->profil($u->fresh());
    }

    /** Change le mot de passe ; les autres sessions sont déconnectées, la session en cours est gardée. */
    public function changerMotDePasse(Utilisateur $u, string $actuel, string $nouveau, ?int $jetonCourantId): void
    {
        $this->verifierMotDePasse($u, $actuel);

        if (Hash::check($nouveau, $u->mot_de_passe_hash)) {
            throw new RegleMetierException('Le nouveau mot de passe doit être différent de l’actuel.', 422);
        }

        DB::transaction(function () use ($u, $nouveau, $jetonCourantId) {
            $u->mot_de_passe_hash = Hash::make($nouveau);
            $u->save();

            $u->tokens()
                ->when($jetonCourantId, fn ($q) => $q->where('id', '!=', $jetonCourantId))
                ->delete();
        });
    }

    // ============== Outils internes ==============

    private function verifierMotDePasse(Utilisateur $u, string $motDePasse): void
    {
        if (! Hash::check($motDePasse, $u->mot_de_passe_hash)) {
            throw new RegleMetierException('Mot de passe incorrect.', 422);
        }
    }

    /** « 77 123 45 67 » → « +221771234567 » ; vide → null ; invalide → erreur. */
    private function telephone(?string $saisi): ?string
    {
        $n = preg_replace('/[\s.\-()]/', '', (string) $saisi);

        if ($n === '') {
            return null;
        }

        $n = preg_replace('/^(\+221|00221|221)/', '', $n);

        if (! preg_match('/^7[05678]\d{7}$/', $n)) {
            throw new RegleMetierException('Numéro sénégalais invalide (ex. 77 123 45 67).', 422);
        }

        return '+221'.$n;
    }
}
