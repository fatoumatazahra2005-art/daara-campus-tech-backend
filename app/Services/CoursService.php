<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\BlocContenu;
use App\Models\Chapitre;
use App\Models\CodeList;
use App\Models\Cours;
use App\Models\EtapeProjetGuide;
use App\Models\Module;
use App\Models\ProjetGuide;
use App\Models\Proposition;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Utilisateur;
use App\Services\Apprenant\ParcoursSuiviService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Cycle de vie d'un cours : Encadreur.creerCours() / soumettreCours(),
 * Administrateur.validerCours() / refuserCours(motif), Cours.archiver().
 *
 * Règle 1 : modifier un cours PUBLIE le repasse en EN_ATTENTE_VALIDATION (revalidation) ; il disparaît du
 * catalogue pour les nouveaux inscrits, reste accessible aux inscrits, et reste modifiable jusqu'à la décision.
 * Seule une vraie modification (wasChanged) déclenche la revalidation.
 */
class CoursService
{
    /** Statuts depuis lesquels un cours peut être archivé. */
    private const STATUTS_ARCHIVABLES = ['PUBLIE', 'EN_ATTENTE_VALIDATION', 'REFUSE'];

    /** Statuts dans lesquels le contenu d'un cours peut être modifié (hors revalidation). */
    private const STATUTS_MODIFIABLES = ['BROUILLON', 'REFUSE', 'PUBLIE'];

    /** Crée un cours au statut BROUILLON dont l'encadreur est l'auteur. */
    public function creer(Utilisateur $encadreur, array $donnees): Cours
    {
        if (! $encadreur->aLeRole('ENCADREUR')) {
            throw new RegleMetierException('Seul un encadreur peut créer un cours.', 403);
        }

        $cours = Cours::create([
            'titre' => $donnees['titre'],
            'description' => $donnees['description'] ?? null,
            'niveau_id' => CodeList::idOf('NiveauApprenant', $donnees['niveau']),
            // Langue : valeur de la CodeList « Langue » (FR, EN), enregistrée dans langue_id.
            'langue_id' => CodeList::idOf('Langue', strtoupper($donnees['langue'] ?? 'FR')),
            'est_certifiant' => $donnees['est_certifiant'] ?? false,
            'image_couverture' => isset($donnees['image_couverture'])
                ? $donnees['image_couverture']->store('cours', 'public')
                : null,
            'statut_id' => CodeList::idOf('StatutCours', 'BROUILLON'),
            'date_creation' => now()->toDateString(),
            'auteur_id' => $encadreur->id,
            'technologie_id' => $donnees['technologie_id'],
        ]);

        return $cours->refresh();
    }

    /**
     * Modifiable en BROUILLON, REFUSE, PUBLIE ou en revalidation (un cours PUBLIE modifié repasse en
     * EN_ATTENTE_VALIDATION). La modification et le changement de statut réussissent ou échouent ensemble.
     * Mêmes données renvoyées : rien n'est enregistré et le statut ne bouge pas.
     */
    public function modifier(Cours $cours, array $donnees): Cours
    {
        if (! $this->estModifiable($cours)) {
            throw new RegleMetierException('Ce cours ne peut pas être modifié dans son état actuel.');
        }

        $ancienneImage = $cours->image_couverture;
        $nouvelleImage = null;

        // Enregistrer la nouvelle image si elle a été envoyée
        if (! empty($donnees['image_couverture'])) {
            $nouvelleImage = $donnees['image_couverture']->store('cours', 'public');
        }

        try {
            $coursModifie = DB::transaction(function () use ($cours, $donnees, $nouvelleImage) {
                $champs = array_intersect_key(
                    $donnees,
                    array_flip([
                        'titre',
                        'description',
                        'est_certifiant',
                        'technologie_id',
                    ])
                );

                // Langue : valeur de la CodeList « Langue » (FR, EN), enregistrée dans langue_id.
                if (isset($donnees['langue'])) {
                    $champs['langue_id'] = CodeList::idOf('Langue', strtoupper($donnees['langue']));
                }

                // Remplacer le chemin uniquement si une nouvelle image existe
                if ($nouvelleImage !== null) {
                    $champs['image_couverture'] = $nouvelleImage;
                }

                if (isset($donnees['niveau'])) {
                    $champs['niveau_id'] = CodeList::idOf('NiveauApprenant', $donnees['niveau']);
                }

                $cours->update($champs);

                // Règle 1 : seule une vraie modification fait repasser un cours publié en validation.
                if ($cours->wasChanged()) {
                    $this->marquerModifie($cours);
                }

                return $cours->refresh();
            });
        } catch (\Throwable $e) {
            // En cas d'échec, supprimer la nouvelle image enregistrée
            if ($nouvelleImage !== null) {
                Storage::disk('public')->delete($nouvelleImage);
            }

            throw $e;
        }

        // Supprimer l'ancienne image seulement après la réussite
        if ($nouvelleImage !== null && $ancienneImage) {
            Storage::disk('public')->delete($ancienneImage);
        }

        return $coursModifie;
    }

    /**
     * Soumet à validation : BROUILLON ou REFUSE vers EN_ATTENTE_VALIDATION (RG1).
     * Exige au moins un module et un chapitre actifs, au moins un bloc valide par chapitre actif.
     * Quiz et projets guidés sont facultatifs ; s'ils existent, ils doivent être valides (RG23).
     */
    public function soumettre(Cours $cours): Cours
    {
        if ($cours->statut?->value === 'EN_ATTENTE_VALIDATION') {
            throw new RegleMetierException(
                'Ce cours a déjà été soumis et attend actuellement la validation de l’administrateur.',
                409
            );
        }

        // 1. Vérifier le statut actuel du cours.
        $this->exigerStatut(
            $cours,
            ['BROUILLON', 'REFUSE'],
            'Seuls les cours en brouillon ou refusés peuvent être soumis à validation.'
        );

        // 2. Récupérer les modules actifs et leurs contenus.
        $modules = $cours->modules()
            ->where('actif', true)
            ->with([
                'chapitres' => fn ($q) => $q->where('actif', true),
                'chapitres.blocs.texte',
                'chapitres.blocs.image',
                'chapitres.blocs.code',
                'chapitres.quiz' => fn ($q) => $q->where('actif', true),
                'chapitres.quiz.mode',
                'chapitres.quiz.questions' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
                'chapitres.quiz.questions.type',
                'chapitres.quiz.questions.propositions' => fn ($q) => $q->where('actif', true)->orderBy('ordre'),
            ])
            ->get();

        // 3. Vérifier que le cours possède au moins un module actif.
        if ($modules->isEmpty()) {
            throw new RegleMetierException('Le cours doit contenir au moins un module actif.');
        }

        // 4. Récupérer tous les chapitres actifs.
        $chapitres = collect();

        foreach ($modules as $module) {
            // Chaque module actif doit avoir au moins un chapitre actif.
            if ($module->chapitres->isEmpty()) {
                throw new RegleMetierException(
                    "Le module « {$module->titre} » doit contenir au moins un chapitre actif."
                );
            }

            foreach ($module->chapitres as $chapitre) {
                $chapitres->push($chapitre);
            }
        }

        // 5. Vérifier les chapitres et leurs blocs de contenu.
        foreach ($chapitres as $chapitre) {
            if (empty(trim($chapitre->titre ?? ''))) {
                throw new RegleMetierException('Un chapitre possède un titre vide.');
            }

            // RG1 : chaque chapitre actif doit avoir au moins un bloc.
            if ($chapitre->blocs->isEmpty()) {
                throw new RegleMetierException(
                    "Le chapitre « {$chapitre->titre} » doit contenir au moins un bloc de contenu."
                );
            }

            // Vérifier chaque bloc selon son type.
            foreach ($chapitre->blocs as $bloc) {
                switch ($bloc->type_bloc) {
                    case 'TEXTE':
                        if (! $bloc->texte || empty(trim($bloc->texte->contenu ?? ''))) {
                            throw new RegleMetierException(
                                "Un bloc texte du chapitre « {$chapitre->titre} » possède un contenu vide."
                            );
                        }
                        break;

                    case 'IMAGE':
                        if (! $bloc->image || empty(trim($bloc->image->url_image ?? ''))) {
                            throw new RegleMetierException(
                                "Un bloc image du chapitre « {$chapitre->titre} » ne possède pas d'image."
                            );
                        }
                        break;

                    case 'CODE':
                        if (
                            ! $bloc->code ||
                            empty(trim($bloc->code->code ?? '')) ||
                            empty(trim($bloc->code->langage ?? ''))
                        ) {
                            throw new RegleMetierException(
                                "Un bloc code du chapitre « {$chapitre->titre} » doit contenir un code et un langage."
                            );
                        }
                        break;

                    default:
                        throw new RegleMetierException(
                            "Le type de bloc « {$bloc->type_bloc} » n'est pas reconnu."
                        );
                }
            }

            // 6. Le quiz est facultatif (Chapitre 1 — Quiz 0..1) ;
            //    s'il existe et qu'il est actif, il doit être valide.
            $quiz = $chapitre->quiz;

            if (! $quiz) {
                continue;
            }

            if (empty(trim($quiz->titre ?? '')) || mb_strlen($quiz->titre) > 150) {
                throw new RegleMetierException(
                    "Le quiz du chapitre « {$chapitre->titre} » possède un titre invalide."
                );
            }

            if (! $quiz->mode || ! $quiz->mode->active || $quiz->mode->type !== 'ModeQuiz') {
                throw new RegleMetierException(
                    "Le mode du quiz du chapitre « {$chapitre->titre} » est invalide ou inactif."
                );
            }

            if (! $quiz->date_creation) {
                throw new RegleMetierException(
                    "Le quiz du chapitre « {$chapitre->titre} » doit posséder une date de création."
                );
            }

            if (
                ! is_numeric($quiz->seuil_reussite) ||
                $quiz->seuil_reussite < 0 ||
                $quiz->seuil_reussite > 100
            ) {
                throw new RegleMetierException(
                    "Le seuil de réussite du quiz du chapitre « {$chapitre->titre} » doit être compris entre 0 et 100."
                );
            }

            if ($quiz->duree_limite !== null && $quiz->duree_limite < 1) {
                throw new RegleMetierException(
                    "La durée du quiz du chapitre « {$chapitre->titre} » doit être supérieure à zéro."
                );
            }

            if ($quiz->xp_bonus < 0) {
                throw new RegleMetierException(
                    "Le bonus XP du quiz du chapitre « {$chapitre->titre} » ne peut pas être négatif."
                );
            }

            $this->validerQuiz($quiz);
        }

        // 7. Projets guidés (facultatifs ; s'ils existent, ils sont vérifiés).
        $projets = $cours->projetsGuides()
            ->with(['etapes.chapitre.module'])
            ->get();

        // 8. Vérifier chaque projet guidé.
        foreach ($projets as $projet) {
            if (empty(trim($projet->titre ?? '')) || mb_strlen($projet->titre) > 150) {
                throw new RegleMetierException('Un projet guidé possède un titre vide ou invalide.');
            }

            if ($projet->etapes->isEmpty()) {
                throw new RegleMetierException(
                    "Le projet guidé « {$projet->titre} » doit contenir au moins une étape."
                );
            }

            // 9. Vérifier les étapes du projet.
            foreach ($projet->etapes as $etape) {
                if (empty(trim($etape->consigne ?? ''))) {
                    throw new RegleMetierException(
                        "Une étape du projet « {$projet->titre} » possède une consigne vide."
                    );
                }

                $chapitre = $etape->chapitre;
                $module = $chapitre?->module;

                if (! $chapitre || ! $chapitre->actif) {
                    throw new RegleMetierException(
                        "Une étape du projet « {$projet->titre} » est associée à un chapitre inexistant ou inactif."
                    );
                }

                if (! $module || ! $module->actif) {
                    throw new RegleMetierException(
                        "Le chapitre associé à une étape du projet « {$projet->titre} » appartient à un module inactif ou inexistant."
                    );
                }

                // Contrainte d'intégrité : le chapitre lié appartient au cours qui porte le projet guidé.
                if ((int) $module->cours_id !== (int) $cours->id) {
                    throw new RegleMetierException(
                        "Une étape du projet « {$projet->titre} » est associée à un chapitre appartenant à un autre cours."
                    );
                }
            }
        }

        // 10. Toutes les vérifications sont réussies : le cours passe en attente de validation.
        $cours->update([
            'statut_id' => CodeList::idOf('StatutCours', 'EN_ATTENTE_VALIDATION'),
            'motif_refus' => null,
        ]);

        return $cours->refresh();
    }

    /**
     * Cours.publier() : EN_ATTENTE_VALIDATION vers PUBLIE ; la date de première publication n'est posée qu'une fois.
     * Règle 5 : les inscrits d'un parcours publié qui contient ce cours le reçoivent maintenant.
     */
    public function valider(Cours $cours, Utilisateur $administrateur): Cours
    {
        $this->exigerAdministrateur($administrateur);
        $this->exigerStatut($cours, ['EN_ATTENTE_VALIDATION'], 'Seul un cours en attente de validation peut être validé.');

        $cours->update([
            'a_relire' => false,
            'statut_id' => CodeList::idOf('StatutCours', 'PUBLIE'),
            'date_publication' => $cours->date_publication ?? now()->toDateString(),
            'valide_par_id' => $administrateur->administrateur->id,
            'motif_refus' => null,
        ]);

        $cours = $cours->refresh();

        app(ParcoursSuiviService::class)->rattacherCoursPublie($cours);

        return $cours;
    }

    /**
     * Cours.refuser(motif) : EN_ATTENTE_VALIDATION vers REFUSE, avec un motif obligatoire.
     * Un cours déjà publié reste lisible par ses inscrits (AccesService::lisiblePourInscrit).
     */
    public function refuser(Cours $cours, Utilisateur $administrateur, string $motif): Cours
    {
        $this->exigerAdministrateur($administrateur);
        $this->exigerStatut($cours, ['EN_ATTENTE_VALIDATION'], 'Seul un cours en attente de validation peut être refusé.');

        if (trim($motif) === '') {
            throw new RegleMetierException('Un refus exige un motif.');
        }

        $cours->update([
            'statut_id' => CodeList::idOf('StatutCours', 'REFUSE'),
            'motif_refus' => $motif,
            'valide_par_id' => $administrateur->administrateur->id,
        ]);

        return $cours->refresh();
    }

    /**
     * Cours.archiver() : retire du catalogue sans supprimer ; le cours reste lisible par ses inscrits (règle 2).
     * Un brouillon n'est pas archivé : il se supprime. Un cours déjà archivé ne l'est pas deux fois.
     */
    public function archiver(Cours $cours): Cours
    {
        $this->exigerHorsParcoursPublie($cours);
        $this->exigerStatut(
            $cours,
            self::STATUTS_ARCHIVABLES,
            'Seul un cours publié, en attente de validation ou refusé peut être archivé ; supprimez un brouillon.'
        );

        $cours->update(['statut_id' => CodeList::idOf('StatutCours', 'ARCHIVE')]);

        return $cours->refresh();
    }

    /** Suppression physique (cascade) d'un brouillon jamais publié. */
    public function supprimer(Cours $cours): void
    {
        if ($cours->statut?->value !== 'BROUILLON' || $cours->date_publication !== null) {
            throw new RegleMetierException('Seul un brouillon jamais publié peut être supprimé ; archivez le cours sinon.');
        }

        if ($cours->parcours()->exists() || $cours->inscriptions()->exists()) {
            throw new RegleMetierException('Ce cours est rattaché à un parcours ou à des inscriptions : archivez-le au lieu de le supprimer.');
        }

        $image = $cours->image_couverture;

        if ($cours->delete() && $image) {
            Storage::disk('public')->delete($image);
        }
    }

    /**
     * Appelée à chaque modification du contenu par l'encadreur, dans la transaction (règle 1).
     * - BROUILLON / REFUSE : le statut ne change pas.
     * - PUBLIE : le cours repasse EN_ATTENTE_VALIDATION (revalidation) ; il disparaît du catalogue pour
     *   les nouveaux inscrits mais reste accessible aux inscrits. a_relire = true : modification d'un cours publié.
     * - EN_ATTENTE_VALIDATION d'un cours déjà publié (revalidation) : modification autorisée, le statut ne change pas.
     * - EN_ATTENTE_VALIDATION d'une première soumission, ou ARCHIVE : modification refusée.
     */
    public function marquerModifie(Cours $cours): void
    {
        $cours->unsetRelation('statut');

        if (! $this->estModifiable($cours)) {
            throw new RegleMetierException(
                $cours->statut?->value === 'EN_ATTENTE_VALIDATION'
                    ? 'Ce cours est en attente de validation : son contenu ne peut pas être modifié avant la décision de l’administrateur.'
                    : 'Ce cours est archivé : son contenu ne peut plus être modifié.'
            );
        }

        if ($cours->statut?->value === 'PUBLIE') {
            $cours->update([
                'statut_id' => CodeList::idOf('StatutCours', 'EN_ATTENTE_VALIDATION'),
                'a_relire' => true,
                'modifie_le' => now(),
            ]);
            $cours->unsetRelation('statut');

            return;
        }

        if ($this->enRevalidation($cours)) {
            $cours->update(['modifie_le' => now()]);
        }
    }

    /** Cours déjà publié, modifié, en attente de la décision de l'administrateur. */
    public function enRevalidation(Cours $cours): bool
    {
        return $cours->statut?->value === 'EN_ATTENTE_VALIDATION' && $cours->date_publication !== null;
    }

    /** BROUILLON, REFUSE, PUBLIE, ou en revalidation (cours déjà publié en attente). */
    public function estModifiable(Cours $cours): bool
    {
        return in_array($cours->statut?->value, self::STATUTS_MODIFIABLES, true) || $this->enRevalidation($cours);
    }

    /**
     * Retire un élément du contenu : suppression physique tant que le cours n'a jamais été publié,
     * suppression logique (actif = false) ensuite, pour conserver progressions, tentatives et réponses (règle 3).
     */
    public function retirer(Model $element, Cours $cours): void
    {
        if ($cours->date_publication !== null && ! $element->isFillable('actif')) {
            throw new \LogicException(class_basename($element).' n\'a pas de colonne « actif » : suppression logique impossible.');
        }

        DB::transaction(function () use ($element, $cours) {
            if ($cours->date_publication !== null) {
                $element->update(['actif' => false]);
            } else {
                $element->delete();
            }

            $this->marquerModifie($cours);
        });
    }

    /** Cours auquel appartient un élément de contenu. */
    public function coursDe(Model $element): Cours
    {
        return match (true) {
            $element instanceof Module => $element->cours,
            $element instanceof Chapitre => $element->module->cours,
            $element instanceof BlocContenu => $element->chapitre->module->cours,
            $element instanceof Quiz => $element->chapitre->module->cours,
            $element instanceof Question => $element->quiz->chapitre->module->cours,
            $element instanceof Proposition => $element->question->quiz->chapitre->module->cours,
            $element instanceof ProjetGuide => $element->cours,
            $element instanceof EtapeProjetGuide => $element->projetGuide->cours,
            default => throw new \InvalidArgumentException(class_basename($element).' n\'appartient pas au contenu d\'un cours.'),
        };
    }

    /**
     * Charge le cours avec sa structure ; $contenuComplet ajoute blocs, quiz et projets guidés.
     * $avecInactifs : l'éditeur de l'encadreur voit aussi les éléments désactivés (pour les réactiver).
     */
    public function charger(Cours $cours, bool $contenuComplet = true, bool $avecInactifs = false): Cours
    {
        $actifs = fn ($q) => $avecInactifs ? $q : $q->where('actif', true);

        $relations = [
            'statut', 'niveau', 'technologie', 'auteur.utilisateur',
            'modules' => fn ($q) => $actifs($q)->orderBy('ordre'),
            'modules.chapitres' => fn ($q) => $actifs($q)->orderBy('ordre'),
        ];

        if ($contenuComplet) {
            $relations += [
                'modules.chapitres.blocs' => fn ($q) => $q,
                'modules.chapitres.blocs.texte' => fn ($q) => $q,
                'modules.chapitres.blocs.image' => fn ($q) => $q,
                'modules.chapitres.blocs.code' => fn ($q) => $q,
                'modules.chapitres.quiz' => fn ($q) => $actifs($q),
                'modules.chapitres.quiz.mode' => fn ($q) => $q,
                'modules.chapitres.quiz.questions' => fn ($q) => $actifs($q)->orderBy('ordre'),
                'modules.chapitres.quiz.questions.type' => fn ($q) => $q,
                'modules.chapitres.quiz.questions.propositions' => fn ($q) => $actifs($q)->orderBy('ordre'),
                'projetsGuides' => fn ($q) => $q->orderBy('id'),
                'projetsGuides.etapes' => fn ($q) => $q->orderBy('ordre'),
                'projetsGuides.etapes.chapitre' => fn ($q) => $q,
            ];
        }

        return $cours->load($relations);
    }

    /** L'administrateur a relu les modifications d'un cours publié (conservé pour compatibilité). */
    public function marquerRelu(Cours $cours): Cours
    {
        if (! $cours->a_relire) {
            throw new RegleMetierException('Ce cours n’a pas de modification à relire.');
        }

        $cours->update(['a_relire' => false]);

        return $cours->refresh();
    }

    private function exigerAdministrateur(Utilisateur $utilisateur): void
    {
        if (! $utilisateur->aLeRole('ADMIN')) {
            throw new RegleMetierException('Seul un administrateur peut valider ou refuser un cours.', 403);
        }

        if (! $utilisateur->administrateur()->exists()) {
            throw new RegleMetierException('Le profil administrateur associé à ce compte est introuvable.', 403);
        }
    }

    /** @param list<string> $statuts */
    private function exigerStatut(Cours $cours, array $statuts, string $message): void
    {
        if (! in_array($cours->statut?->value, $statuts, true)) {
            throw new RegleMetierException($message);
        }
    }

    /** Vérifie les questions et propositions d'un quiz actif (Quiz 1..* Question, RG23). */
    private function validerQuiz(Quiz $quiz): void
    {
        $questions = $quiz->questions;

        if ($questions->isEmpty()) {
            throw new RegleMetierException(
                "Le quiz « {$quiz->titre} » doit contenir au moins une question active."
            );
        }

        foreach ($questions as $question) {
            if (empty(trim($question->enonce ?? ''))) {
                throw new RegleMetierException(
                    "Une question du quiz « {$quiz->titre} » possède un énoncé vide."
                );
            }

            if ($question->points < 1) {
                throw new RegleMetierException(
                    "La question « {$question->enonce} » doit avoir au moins un point."
                );
            }

            $propositions = $question->propositions;

            foreach ($propositions as $proposition) {
                if (empty(trim($proposition->texte ?? ''))) {
                    throw new RegleMetierException(
                        "Une proposition de la question « {$question->enonce} » possède un texte vide."
                    );
                }
            }

            $nombreBonnesReponses = $propositions->where('est_correcte', true)->count();

            $typeQuestion = $question->type;

            if (! $typeQuestion || ! $typeQuestion->active || $typeQuestion->type !== 'TypeQuestion') {
                throw new RegleMetierException(
                    "Le type de la question « {$question->enonce} » est invalide ou inactif."
                );
            }

            $type = $typeQuestion->value;

            switch ($type) {
                case 'VRAI_FAUX':
                    if ($propositions->count() !== 2 || $nombreBonnesReponses !== 1) {
                        throw new RegleMetierException(
                            "La question « {$question->enonce} » de type Vrai/Faux doit avoir exactement deux propositions et une seule bonne réponse."
                        );
                    }
                    break;

                case 'CHOIX_UNIQUE':
                    if ($propositions->count() < 2 || $nombreBonnesReponses !== 1) {
                        throw new RegleMetierException(
                            "La question « {$question->enonce} » doit avoir au moins deux propositions et exactement une bonne réponse."
                        );
                    }
                    break;

                case 'CHOIX_MULTIPLE':
                    if (
                        $propositions->count() < 3 ||
                        $nombreBonnesReponses < 1 ||
                        $nombreBonnesReponses === $propositions->count()
                    ) {
                        throw new RegleMetierException(
                            "La question « {$question->enonce} » à choix multiple doit avoir au moins trois propositions, "
                            . "dont au moins une bonne réponse et au moins une mauvaise."
                        );
                    }
                    break;

                default:
                    throw new RegleMetierException(
                        "Le type de question « {$type} » n'est pas reconnu."
                    );
            }
        }
    }

    /** Règle : un parcours publié ne contient que des cours publiés. */
    private function exigerHorsParcoursPublie(Cours $cours): void
    {
        $parcours = $cours->parcours()
            ->whereHas('statut', fn ($q) => $q->where('value', 'PUBLIE'))
            ->pluck('titre');

        if ($parcours->isNotEmpty()) {
            throw new RegleMetierException(
                'Impossible d’archiver ce cours : il fait partie du parcours publié « '
                . $parcours->implode(' », « ')
                . ' ». Il doit d’abord être retiré du parcours.'
            );
        }
    }
}
