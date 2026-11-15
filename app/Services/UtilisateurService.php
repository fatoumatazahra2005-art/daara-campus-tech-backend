<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Administrateur;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\Encadreur;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Gestion des comptes par l'administrateur :
 * création (ADMIN / ENCADREUR), modification, statut (RG32), niveau, mot de passe, suppression.
 */
class UtilisateurService
{
    /**
     * Transitions autorisées pour les statuts des comptes.
     * Un compte BLOQUE ou DESACTIVE peut être réactivé.
     */
    private const TRANSITIONS = [
        'bloquer' => [
            'de' => ['ACTIF'],
            'vers' => 'BLOQUE',
            'verbe' => 'bloquer',
        ],

        'desactiver' => [
            'de' => ['ACTIF', 'BLOQUE'],
            'vers' => 'DESACTIVE',
            'verbe' => 'désactiver',
        ],

        'activer' => [
            'de' => ['DESACTIVE', 'BLOQUE'],
            'vers' => 'ACTIF',
            'verbe' => 'activer',
        ],
    ];

    /** Relations chargées pour le détail d'un compte. */
    private const RELATIONS_DETAIL = [
        'role',
        'statutCompte',
        'apprenant.niveau',
        'encadreur',
        'administrateur',
    ];

    // =====================================================================
    // Création
    // =====================================================================

    /**
     * Créer un compte administrateur ou encadreur.
     * Les apprenants s'inscrivent eux-mêmes depuis l'application mobile.
     */
    public function creer(array $donnees): Utilisateur
    {
        $role = $donnees['role'] ?? null;

        if (!in_array($role, ['ADMIN', 'ENCADREUR'], true)) {
            throw new RegleMetierException(
                'Seuls les comptes administrateur et encadreur peuvent être créés ici.'
            );
        }

        $utilisateur = DB::transaction(function () use ($donnees, $role) {

            $utilisateur = Utilisateur::create([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'] ?? null,
                'mot_de_passe_hash' => Hash::make($donnees['password']),
                'role_id' => CodeList::idOf('RoleUser', $role),
                'statut_compte_id' => CodeList::idOf('StatutCompte', 'ACTIF'),
            ]);

            // Sous-classe correspondant au rôle (même id que l'utilisateur).
            match ($role) {
                'ADMIN' => Administrateur::create([
                    'id' => $utilisateur->id,
                ]),

                'ENCADREUR' => Encadreur::create([
                    'id' => $utilisateur->id,
                    'specialite' => $donnees['specialite'] ?? null,
                ]),
            };

            return $utilisateur;
        });

        return $utilisateur->refresh()->load(self::RELATIONS_DETAIL);
    }

    // =====================================================================
    // Statut du compte (RG32)
    // =====================================================================

    /**
     * Bloquer, désactiver ou réactiver un compte.
     */
    public function changerStatut(
        Utilisateur $cible,
        Utilisateur $acteur,
        string $action
    ): Utilisateur {

        if (!isset(self::TRANSITIONS[$action])) {
            throw new RegleMetierException('Action de changement de statut invalide.');
        }

        $transition = self::TRANSITIONS[$action];

        $cible->refresh()->load(['role', 'statutCompte']);

        // Un administrateur ne peut être ni bloqué ni désactivé.
        if ($cible->aLeRole('ADMIN') && $transition['vers'] !== 'ACTIF') {
            throw new RegleMetierException(
                'Vous ne pouvez pas bloquer ou désactiver le compte d’un administrateur.'
            );
        }

        // On ne peut pas modifier son propre statut.
        if ($cible->id === $acteur->id && $transition['vers'] !== 'ACTIF') {
            throw new RegleMetierException('Vous ne pouvez pas modifier votre propre statut.');
        }

        $statutActuel = $cible->statutCompte?->value;

        if (!in_array($statutActuel, $transition['de'], true)) {
            throw new RegleMetierException(
                'Impossible de ' . $transition['verbe']
                . ' un compte au statut ' . $statutActuel . '.'
            );
        }

        DB::transaction(function () use ($cible, $transition) {

            $cible->update([
                'statut_compte_id' => CodeList::idOf('StatutCompte', $transition['vers']),
            ]);

            // Un compte inactif est déconnecté de tous ses appareils.
            if ($transition['vers'] !== 'ACTIF') {
                $cible->tokens()->delete();
            }
        });

        return $cible->refresh()->load(self::RELATIONS_DETAIL);
    }

    // =====================================================================
    // Modification
    // =====================================================================

    /**
     * Modifier les informations d'un administrateur ou d'un encadreur.
     * Un apprenant gère lui-même son profil depuis l'application mobile.
     */
    public function modifier(Utilisateur $cible, array $donnees): Utilisateur
    {
        $cible->loadMissing('role');

        if (!in_array($cible->role?->value, ['ADMIN', 'ENCADREUR'], true)) {
            throw new RegleMetierException(
                'Seuls les comptes administrateur et encadreur peuvent être modifiés ici.'
            );
        }

        DB::transaction(function () use ($cible, $donnees) {

            $cible->update([
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'] ?? null,
            ]);

            // Spécialité modifiée uniquement si elle est envoyée.
            if ($cible->role->value === 'ENCADREUR' && array_key_exists('specialite', $donnees)) {
                $cible->encadreur()->update([
                    'specialite' => $donnees['specialite'],
                ]);
            }
        });

        return $cible->refresh()->load(self::RELATIONS_DETAIL);
    }

    // =====================================================================
    // Consultation
    // =====================================================================

    /**
     * Consulter un utilisateur par son identifiant.
     */
    public function consulter(int $id): Utilisateur
    {
        $utilisateur = Utilisateur::with(self::RELATIONS_DETAIL)->find($id);

        if (!$utilisateur) {
            throw new RegleMetierException('Utilisateur introuvable.', 404);
        }

        return $utilisateur;
    }

    /**
     * Lister les utilisateurs avec recherche, filtres et pagination.
     */
    public function lister(array $filtres = [], int $parPage = 10)
    {
        $parPage = max(1, min($parPage, 50));

        $requete = Utilisateur::with(['role', 'statutCompte']);

        // Recherche insensible à la casse (PostgreSQL : ilike).
        if (!empty($filtres['recherche'])) {
            $recherche = '%' . trim($filtres['recherche']) . '%';

            $requete->where(function (Builder $query) use ($recherche) {
                $query->where('nom', 'ilike', $recherche)
                    ->orWhere('prenom', 'ilike', $recherche)
                    ->orWhere('email', 'ilike', $recherche);
            });
        }

        if (!empty($filtres['role'])) {
            $requete->whereHas('role', fn (Builder $q) => $q->where('value', $filtres['role']));
        }

        if (!empty($filtres['statut'])) {
            $requete->whereHas('statutCompte', fn (Builder $q) => $q->where('value', $filtres['statut']));
        }

        return $requete
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($parPage)
            ->withQueryString();
    }

    // =====================================================================
    // Niveau et mot de passe
    // =====================================================================

    /**
     * Modifier le niveau pédagogique d'un apprenant.
     */
    public function modifierNiveau(int $utilisateurId, string $niveau): Utilisateur
    {
        return DB::transaction(function () use ($utilisateurId, $niveau) {

            $utilisateur = Utilisateur::with(['role', 'apprenant'])->find($utilisateurId);

            if (!$utilisateur) {
                throw new RegleMetierException('Utilisateur introuvable.', 404);
            }

            if (!$utilisateur->aLeRole('APPRENANT') || !$utilisateur->apprenant) {
                throw new RegleMetierException('Cet utilisateur ne possède pas de profil apprenant.');
            }

            $utilisateur->apprenant->update([
                'niveau_id' => CodeList::idOf('NiveauApprenant', $niveau),
            ]);

            return $utilisateur->refresh()->load(self::RELATIONS_DETAIL);
        });
    }

    /**
     * Réinitialiser le mot de passe d'un utilisateur.
     * Toutes ses sessions sont fermées.
     */
    public function reinitialiserMotDePasse(int $utilisateurId, string $nouveauMotDePasse): Utilisateur
    {
        $utilisateur = Utilisateur::find($utilisateurId);

        if (!$utilisateur) {
            throw new RegleMetierException('Utilisateur introuvable.', 404);
        }

        DB::transaction(function () use ($utilisateur, $nouveauMotDePasse) {

            $utilisateur->update([
                'mot_de_passe_hash' => Hash::make($nouveauMotDePasse),
            ]);

            $utilisateur->tokens()->delete();
        });

        return $utilisateur->refresh()->load(self::RELATIONS_DETAIL);
    }

    // =====================================================================
    // Suppression (RG32)
    // =====================================================================

    /**
     * Supprimer un compte uniquement s'il n'a aucune activité.
     * Sinon, il doit être désactivé pour conserver l'historique (RG32).
     */
    public function supprimer(Utilisateur $cible): void
    {
        DB::transaction(function () use ($cible) {

            $utilisateur = Utilisateur::with('role')
                ->whereKey($cible->id)
                ->lockForUpdate()
                ->first();

            if (!$utilisateur) {
                throw new RegleMetierException('Utilisateur introuvable.', 404);
            }

            if ($utilisateur->aLeRole('ADMIN')) {
                throw new RegleMetierException('Un compte administrateur ne peut pas être supprimé.');
            }

            // Activité sur le forum.
            $activitesForum = [
                'sujets' => 'des sujets de discussion',
                'messages' => 'des messages sur le forum',
                'votes' => 'des votes',
                'signalements' => 'des signalements',
            ];

            foreach ($activitesForum as $relation => $description) {
                if (method_exists($utilisateur, $relation) && $utilisateur->{$relation}()->exists()) {
                    $this->refuserSuppression($description);
                }
            }

            // Encadreur : cours créés.
            if ($utilisateur->aLeRole('ENCADREUR')
                && Cours::where('auteur_id', $utilisateur->id)->exists()) {
                $this->refuserSuppression('des cours');
            }

            // Apprenant : historique pédagogique et financier.
            // Apprenant : historique pédagogique et financier.
            if ($apprenant = $utilisateur->apprenant) {
                $historique = [
                    'inscriptionsCours' => 'des inscriptions à des cours',
                    'inscriptionsParcours' => 'des inscriptions à des parcours',
                    'abonnements' => 'des abonnements',
                    'tentatives' => 'des tentatives de quiz',
                    'rendus' => 'des rendus de projet',
                ];

                foreach ($historique as $relation => $description) {
                    if ($apprenant->{$relation}()->exists()) {
                        $this->refuserSuppression($description);
                    }
                }
            }

            try {
                $utilisateur->apprenant()->delete();
                $utilisateur->encadreur()->delete();
                $utilisateur->tokens()->delete();
                $utilisateur->delete();
            } catch (QueryException) {
                // Filet de sécurité : une clé étrangère non listée ci-dessus.
                $this->refuserSuppression('un historique sur la plateforme');
            }
        });
    }

    private function refuserSuppression(string $description): never
    {
        throw new RegleMetierException(
            "Impossible de supprimer ce compte : il possède {$description}. Désactivez-le plutôt (RG32)."
        );
    }
}
