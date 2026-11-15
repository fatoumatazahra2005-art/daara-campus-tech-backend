<?php

namespace App\Policies;

use App\Models\Cours;
use App\Models\Utilisateur;
use Illuminate\Auth\Access\Response;

/**
 * Statuts d'un cours : BROUILLON, EN_ATTENTE_VALIDATION, PUBLIE, REFUSE, ARCHIVE.
 * Le cours est créé et géré par son encadreur ; l'administrateur le valide, le refuse ou l'archive.
 * Chaque refus renvoie un message explicite (affiché tel quel dans l'interface).
 */
class CoursPolicy
{
    /** Statuts dans lesquels l'auteur peut modifier le cours et son contenu (RG1, RG2). */
    private const STATUTS_MODIFIABLES = ['BROUILLON', 'REFUSE', 'PUBLIE'];

    /** Statuts depuis lesquels un cours peut être archivé (un brouillon se supprime). */
    private const STATUTS_ARCHIVABLES = ['PUBLIE', 'EN_ATTENTE_VALIDATION', 'REFUSE'];

    private function statut(Cours $cours): ?string
    {
        return $cours->statut?->value;
    }

    private function estAuteur(Utilisateur $utilisateur, Cours $cours): bool
    {
        return (int) $cours->auteur_id === (int) $utilisateur->id;
    }

    /** Message expliquant pourquoi un cours n'est pas modifiable dans son statut actuel. */
    private function messageStatut(Cours $cours): string
    {
        return match ($this->statut($cours)) {
            'EN_ATTENTE_VALIDATION' => 'Ce cours est en attente de validation : il ne peut pas être modifié avant la décision de l’administrateur.',
            'ARCHIVE' => 'Ce cours est archivé : il ne peut plus être modifié.',
            default => 'Ce cours ne peut pas être modifié dans son état actuel.',
        };
    }

    /** Un cours publié est visible par tous ; sinon seulement par son auteur et les administrateurs. */
    public function view(Utilisateur $utilisateur, Cours $cours): Response
    {
        return $this->statut($cours) === 'PUBLIE' || $this->peutVoirContenu($utilisateur, $cours)
            ? Response::allow()
            : Response::deny("Ce cours n'est pas encore publié.");
    }

    /** Contenu complet (blocs, quiz, bonnes réponses) : l'auteur et les administrateurs. */
    public function voirContenu(Utilisateur $utilisateur, Cours $cours): Response
    {
        return $this->peutVoirContenu($utilisateur, $cours)
            ? Response::allow()
            : Response::deny("Seuls l'auteur du cours et les administrateurs peuvent voir son contenu complet.");
    }

    private function peutVoirContenu(Utilisateur $utilisateur, Cours $cours): bool
    {
        return $utilisateur->aLeRole('ADMIN') || $this->estAuteur($utilisateur, $cours);
    }

    public function create(Utilisateur $utilisateur): Response
    {
        return $utilisateur->aLeRole('ENCADREUR')
            ? Response::allow()
            : Response::deny('Seul un encadreur peut créer un cours.');
    }

    /**
     * Modifiable par l'auteur en BROUILLON, REFUSE ou PUBLIE.
     * Modifier un cours PUBLIE le repasse en EN_ATTENTE_VALIDATION (RG1).
     */
    /**
     * Modifiable par l'auteur en BROUILLON, REFUSE ou PUBLIE (RG1, RG2),
     * et pendant la revalidation d'un cours déjà publié (EN_ATTENTE_VALIDATION avec date_publication) :
     * l'encadreur termine ses changements avant la décision de l'administrateur.
     * Une première soumission en attente n'est pas modifiable.
     */
    public function update(Utilisateur $utilisateur, Cours $cours): Response
    {
        if (! $this->estAuteur($utilisateur, $cours)) {
            return Response::deny("Vous n'êtes pas l'auteur de ce cours.");
        }

        if (! in_array($this->statut($cours), self::STATUTS_MODIFIABLES, true) && ! $this->enRevalidation($cours)) {
            return Response::deny($this->messageStatut($cours));
        }

        return Response::allow();
    }

    /** Cours déjà publié, modifié, en attente de la décision de l'administrateur (règle 1). */
    private function enRevalidation(Cours $cours): bool
    {
        return $this->statut($cours) === 'EN_ATTENTE_VALIDATION' && $cours->date_publication !== null;
    }

    /** Soumission : l'auteur encadreur. Le statut et le contenu sont vérifiés par CoursService::soumettre(). */
    public function soumettre(Utilisateur $utilisateur, Cours $cours): Response
    {
        if (! $utilisateur->aLeRole('ENCADREUR')) {
            return Response::deny('Seul un encadreur peut soumettre un cours.');
        }

        if (! $this->estAuteur($utilisateur, $cours)) {
            return Response::deny("Vous n'êtes pas l'auteur de ce cours.");
        }

        return Response::allow();
    }

    /** Suppression physique : seulement un brouillon qui n'a jamais été publié (RG3). */
    public function supprimer(Utilisateur $utilisateur, Cours $cours): Response
    {
        if (! $this->estAuteur($utilisateur, $cours)) {
            return Response::deny("Vous n'êtes pas l'auteur de ce cours.");
        }

        if ($this->statut($cours) !== 'BROUILLON' || $cours->date_publication !== null) {
            return Response::deny(
                $this->statut($cours) === 'EN_ATTENTE_VALIDATION'
                    ? 'Ce cours est en attente de validation : il ne peut pas être supprimé.'
                    : 'Seul un brouillon jamais publié peut être supprimé ; archivez ce cours à la place.'
            );
        }

        return Response::allow();
    }

    /** Archivable depuis PUBLIE, EN_ATTENTE_VALIDATION ou REFUSE (un brouillon se supprime). */
    /**
     * Archiver retire un cours du catalogue sans le supprimer (RG2) ; l'opération est définitive.
     * - Administrateur : cours publié, en attente ou refusé.
     * - Auteur : uniquement son cours publié (un brouillon se supprime, un cours refusé se corrige).
     */
    public function archiver(Utilisateur $utilisateur, Cours $cours): Response
    {
        $statut = $this->statut($cours);

        if ($statut === 'ARCHIVE') {
            return Response::deny('Ce cours est déjà archivé.');
        }

        if ($utilisateur->aLeRole('ADMIN')) {
            return in_array($statut, self::STATUTS_ARCHIVABLES, true)
                ? Response::allow()
                : Response::deny('Un brouillon ne s’archive pas : son auteur peut le supprimer.');
        }

        if (! $this->estAuteur($utilisateur, $cours)) {
            return Response::deny("Seuls l'auteur du cours et les administrateurs peuvent l'archiver.");
        }

        return match ($statut) {
            'PUBLIE' => Response::allow(),
            'EN_ATTENTE_VALIDATION' => Response::deny(
                'Ce cours est en attente de validation : il ne peut pas être archivé avant la décision de l’administrateur.'
            ),
            'REFUSE' => Response::deny('Un cours refusé ne s’archive pas : corrigez-le et soumettez-le de nouveau.'),
            default => Response::deny('Un brouillon ne s’archive pas : supprimez-le.'),
        };
    }

    public function valider(Utilisateur $utilisateur, Cours $cours): Response
    {
        if (! $utilisateur->aLeRole('ADMIN')) {
            return Response::deny('Seul un administrateur peut valider ou refuser un cours.');
        }

        if ($this->statut($cours) !== 'EN_ATTENTE_VALIDATION') {
            return Response::deny('Seul un cours en attente de validation peut être validé ou refusé.');
        }

        return Response::allow();
    }

    public function refuser(Utilisateur $utilisateur, Cours $cours): Response
    {
        return $this->valider($utilisateur, $cours);
    }
}
