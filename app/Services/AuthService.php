<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Apprenant;
use App\Models\CodeList;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Utilisateur.seConnecter() / seDeconnecter() et inscription d'un apprenant. */
class AuthService
{
    /**
     * Inscription libre : uniquement un apprenant.
     * Le statut ACTIF et le niveau DEBUTANT sont posés par les déclencheurs PostgreSQL.
     *
     * @param array{nom: string, prenom: string, email: string, telephone?: ?string, password: string} $donnees
     * @return array{utilisateur: Utilisateur, token: string}
     */

    public function inscrire(array $donnees): array
    {
        $utilisateur = DB::transaction(function () use ($donnees) {

            // 1. Création du compte utilisateur
            $utilisateur = Utilisateur::create([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'] ?? null,
                'mot_de_passe_hash' => Hash::make($donnees['password']),

                // Attribution automatique du rôle
                'role_id' => CodeList::idOf('RoleUser', 'APPRENANT'),

                // Attribution automatique du statut
                'statut_compte_id' => CodeList::idOf('StatutCompte', 'ACTIF'),
            ]);

            // 2. Création du profil apprenant
            Apprenant::create([
                'id' => $utilisateur->id,

                // Niveau débutant attribué automatiquement
                'niveau_id' => CodeList::idOf(
                    'NiveauApprenant',
                    'DEBUTANT'
                ),
            ]);

            return $utilisateur;
        });

        // 3. Chargement des relations
        $utilisateur->refresh()->load([
            'role',
            'statutCompte',
            'apprenant.niveau',
        ]);

        // 4. Génération du token
        return [
            'utilisateur' => $utilisateur,
            'token' => $utilisateur->createToken('mobile')->plainTextToken
        ];
    }


    /**
     * Authentifie par e-mail et mot de passe ; refusé si statutCompte ≠ ACTIF.
     *
     * @return array{utilisateur: Utilisateur, token: string}
     * @throws RegleMetierException 401 identifiants incorrects, 403 compte inactif
     */
    /**
     * Authentifie par e-mail et mot de passe ; refusé si statutCompte ≠ ACTIF
     * ou si le rôle n'est pas autorisé sur cette application.
     *
     * @param string[] $rolesAutorises ex. ['APPRENANT'] (mobile) ou ['ADMIN', 'ENCADREUR'] (web)
     * @return array{utilisateur: Utilisateur, token: string}
     * @throws RegleMetierException 401 identifiants incorrects, 403 compte inactif ou rôle non autorisé
     */
    public function connecter(
        string $email,
        string $motDePasse,
        string $appareil = 'mobile',
        array $rolesAutorises = ['APPRENANT'],
    ): array {
        $utilisateur = Utilisateur::with([
            'role',
            'statutCompte',
            'apprenant.niveau',
            'encadreur',
        ])->where('email', $email)->first();

        if (! $utilisateur || ! Hash::check($motDePasse, $utilisateur->mot_de_passe_hash)) {
            throw new RegleMetierException('Identifiants incorrects.', 401);
        }

        if (! $utilisateur->estActif()) {
            throw new RegleMetierException('Ce compte est bloqué ou désactivé.', 403);
        }

        if (! in_array($utilisateur->role?->value, $rolesAutorises, true)) {
            throw new RegleMetierException(
                in_array('APPRENANT', $rolesAutorises, true)
                    ? 'Cette application est réservée aux apprenants.'
                    : 'Cet espace est réservé aux encadreurs et aux administrateurs.',
                403
            );
        }

        return [
            'utilisateur' => $utilisateur,
            'token' => $utilisateur->createToken($appareil)->plainTextToken,
        ];
    }

    public function deconnecter(Utilisateur $utilisateur): void
    {
        $token = $utilisateur->currentAccessToken();

        if ($token) {
            $token->delete();
        }
    }
}
