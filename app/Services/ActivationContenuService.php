<?php

namespace App\Services;

use App\Exceptions\RegleMetierException;
use App\Models\Chapitre;
use App\Models\EtapeProjetGuide;
use App\Models\Module;
use App\Models\ProjetGuide;
use App\Models\Proposition;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\Apprenant\ProgressionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Activation / désactivation (suppression logique, règle 3) du contenu d'un cours :
 * modules, chapitres, quiz, questions, propositions, projets guidés et leurs étapes. Les données liées sont conservées.
 * Les contrôles reprennent ceux de la soumission (CoursService::soumettre / validerQuiz),
 * pour que le cours reste toujours soumissible.
 */
class ActivationContenuService
{
    public function __construct(
        private readonly CoursService $cours,
        private readonly ProgressionService $progression,
    ) {
    }

    public function desactiver(Model $element): Model
    {
        return $this->changer($element, false);
    }

    public function activer(Model $element): Model
    {
        return $this->changer($element, true);
    }

    private function changer(Model $element, bool $actif): Model
    {
        [$element, $change] = DB::transaction(function () use ($element, $actif) {
            $element->refresh();

            if ((bool) $element->actif === $actif) {
                return [$element, false]; // Déjà dans l'état demandé.
            }

            match (true) {
                $element instanceof Module => $actif ? $this->peutActiverModule($element) : $this->peutDesactiverModule($element),
                $element instanceof Chapitre => $actif ? $this->peutActiverChapitre($element) : $this->peutDesactiverChapitre($element),
                $element instanceof Quiz => $actif ? $this->peutActiverQuiz($element) : null,
                $element instanceof Question => $actif ? $this->peutActiverQuestion($element) : $this->peutDesactiverQuestion($element),
                $element instanceof Proposition => $this->peutChangerProposition($element, $actif),
                $element instanceof ProjetGuide => $actif ? $this->peutActiverProjetGuide($element) : null,
                $element instanceof EtapeProjetGuide => $actif ? $this->peutActiverEtape($element) : $this->peutDesactiverEtape($element),
                default => throw new \InvalidArgumentException(class_basename($element).' ne peut pas être activé ou désactivé.'),
            };

            $element->update(['actif' => $actif]);

            // Règle 1 : un cours publié modifié repasse en validation (refus si première soumission ou archivé).
            $this->cours->marquerModifie($this->cours->coursDe($element));

            return [$element->refresh(), true];
        });

        // Règle 4 : recalcul des inscriptions EN_COURS, après la transaction.
        // Seuls les modules, chapitres et quiz changent ce qu'il faut terminer.
        // Projet guidé et étapes : ils ne sont vérifiés qu'au moment de terminer un chapitre ;
        // un chapitre déjà terminé le reste (même principe que pour un quiz ajouté après coup).
        if ($change && ($element instanceof Module || $element instanceof Chapitre || $element instanceof Quiz)) {
            $this->progression->recalculerCours($this->cours->coursDe($element)->id);
        }

        return $element;
    }

    // ============== Modules ==============

    /** Le cours garde au moins un module actif ; aucune étape de projet guidé ne doit en dépendre. */
    private function peutDesactiverModule(Module $module): void
    {
        $autres = Module::where('cours_id', $module->cours_id)
            ->where('id', '!=', $module->id)
            ->where('actif', true)
            ->exists();

        if (! $autres) {
            throw new RegleMetierException('C’est le dernier module actif du cours : le cours doit en garder au moins un.');
        }

        $chapitres = Chapitre::where('module_id', $module->id)->where('actif', true)->pluck('id');
        $this->exigerSansEtapeProjet($chapitres, 'Ce module contient un chapitre qui');
    }

    /** Un module actif doit contenir au moins un chapitre actif. */
    private function peutActiverModule(Module $module): void
    {
        if (! Chapitre::where('module_id', $module->id)->where('actif', true)->exists()) {
            throw new RegleMetierException('Ce module n’a aucun chapitre actif : activez d’abord un de ses chapitres.');
        }
    }

    // ============== Chapitres ==============

    /** Un module actif garde au moins un chapitre actif ; aucune étape de projet guidé ne doit en dépendre. */
    private function peutDesactiverChapitre(Chapitre $chapitre): void
    {
        $module = $chapitre->module;

        if ($module?->actif) {
            $autres = Chapitre::where('module_id', $chapitre->module_id)
                ->where('id', '!=', $chapitre->id)
                ->where('actif', true)
                ->exists();

            if (! $autres) {
                throw new RegleMetierException(
                    "C’est le dernier chapitre actif du module « {$module->titre} » : désactivez plutôt le module."
                );
            }
        }

        $this->exigerSansEtapeProjet(collect([$chapitre->id]), 'Ce chapitre');
    }

    /** Un chapitre actif doit contenir au moins un bloc. */
    private function peutActiverChapitre(Chapitre $chapitre): void
    {
        if (! $chapitre->blocs()->exists()) {
            throw new RegleMetierException('Ce chapitre n’a aucun bloc de contenu : ajoutez-en un avant de l’activer.');
        }
    }

    // ============== Quiz ==============

    /** Un chapitre possède au plus un quiz actif ; un quiz actif a au moins une question active valide. */
    private function peutActiverQuiz(Quiz $quiz): void
    {
        $autre = Quiz::where('chapitre_id', $quiz->chapitre_id)
            ->where('id', '!=', $quiz->id)
            ->where('actif', true)
            ->exists();

        if ($autre) {
            throw new RegleMetierException('Ce chapitre a déjà un quiz actif : un chapitre possède au plus un quiz.');
        }

        $questions = Question::where('quiz_id', $quiz->id)->where('actif', true)->get();

        if ($questions->isEmpty()) {
            throw new RegleMetierException('Ce quiz n’a aucune question active : activez ou ajoutez d’abord une question.');
        }

        foreach ($questions as $question) {
            $this->verifierPropositions($question, $this->propositionsActives($question));
        }
    }

    // ============== Questions ==============

    /** Un quiz actif garde au moins une question active. */
    private function peutDesactiverQuestion(Question $question): void
    {
        if (! $question->quiz?->actif) {
            return;
        }

        $autres = Question::where('quiz_id', $question->quiz_id)
            ->where('id', '!=', $question->id)
            ->where('actif', true)
            ->exists();

        if (! $autres) {
            throw new RegleMetierException('C’est la dernière question active du quiz : désactivez plutôt le quiz.');
        }
    }

    /** Une question réactivée doit respecter les règles de son type avec ses propositions actives. */
    private function peutActiverQuestion(Question $question): void
    {
        $this->verifierPropositions($question, $this->propositionsActives($question));
    }

    // ============== Propositions ==============

    /** Les propositions actives restantes doivent respecter les règles du type de question. */
    private function peutChangerProposition(Proposition $proposition, bool $actif): void
    {
        $question = $proposition->question;

        // Une question inactive n'est pas contrôlée : elle le sera à sa réactivation.
        if (! $question?->actif) {
            return;
        }

        $propositions = $this->propositionsActives($question)->reject(fn ($p) => $p->id === $proposition->id);

        if ($actif) {
            $propositions->push($proposition);
        }

        $this->verifierPropositions($question, $propositions);
    }

    // ============== Projets guidés ==============

    /** Un projet guidé actif a au moins une étape active, liée à un chapitre actif d'un module actif. */
    private function peutActiverProjetGuide(ProjetGuide $projet): void
    {
        $etapes = EtapeProjetGuide::with('chapitre.module')
            ->where('projet_guide_id', $projet->id)
            ->where('actif', true)
            ->get();

        if ($etapes->isEmpty()) {
            throw new RegleMetierException('Ce projet guidé n’a aucune étape active : activez ou ajoutez d’abord une étape.');
        }

        foreach ($etapes as $etape) {
            $this->exigerChapitreActif($etape);
        }
    }

    // ============== Étapes de projet guidé ==============

    /** Un projet guidé actif garde au moins une étape active. */
    private function peutDesactiverEtape(EtapeProjetGuide $etape): void
    {
        if (! $etape->projetGuide?->actif) {
            return;
        }

        $autres = EtapeProjetGuide::where('projet_guide_id', $etape->projet_guide_id)
            ->where('id', '!=', $etape->id)
            ->where('actif', true)
            ->exists();

        if (! $autres) {
            throw new RegleMetierException('C’est la dernière étape active du projet guidé : désactivez plutôt le projet guidé.');
        }
    }

    /** Une étape réactivée doit être liée à un chapitre actif d'un module actif. */
    private function peutActiverEtape(EtapeProjetGuide $etape): void
    {
        $etape->loadMissing('chapitre.module');
        $this->exigerChapitreActif($etape);
    }

    private function exigerChapitreActif(EtapeProjetGuide $etape): void
    {
        $chapitre = $etape->chapitre;

        if (! $chapitre || ! $chapitre->actif || ! $chapitre->module?->actif) {
            $consigne = mb_strimwidth((string) $etape->consigne, 0, 50, '…');

            throw new RegleMetierException(
                "L’étape « {$consigne} » est liée à un chapitre inactif : associez-la d’abord à un chapitre actif."
            );
        }
    }

    // ============== Outils ==============

    private function propositionsActives(Question $question): Collection
    {
        return Proposition::where('question_id', $question->id)->where('actif', true)->get();
    }

    /** Règles des propositions selon le type (mêmes règles que CoursService::validerQuiz). */
    private function verifierPropositions(Question $question, Collection $propositions): void
    {
        $type = $question->type?->value;
        $nb = $propositions->count();
        $correctes = $propositions->filter(fn ($p) => (bool) $p->est_correcte)->count();
        $enonce = mb_strimwidth((string) $question->enonce, 0, 60, '…');

        $erreur = match ($type) {
            'VRAI_FAUX' => ($nb !== 2 || $correctes !== 1)
                ? "La question « {$enonce} » (Vrai/Faux) doit garder exactement 2 propositions actives, dont une correcte."
                : null,
            'CHOIX_UNIQUE' => ($nb < 2 || $correctes !== 1)
                ? "La question « {$enonce} » (choix unique) doit garder au moins 2 propositions actives, dont exactement une correcte."
                : null,
            'CHOIX_MULTIPLE' => ($nb < 3 || $correctes < 1 || $correctes === $nb)
                ? "La question « {$enonce} » (choix multiple) doit garder au moins 3 propositions actives, avec au moins une correcte et une incorrecte."
                : null,
            default => null,
        };

        if ($erreur) {
            throw new RegleMetierException($erreur);
        }
    }

    /** Une étape active d'un projet guidé actif ne peut pas pointer vers un chapitre inactif. */
    private function exigerSansEtapeProjet(Collection $chapitreIds, string $sujet): void
    {
        if ($chapitreIds->isEmpty()) {
            return;
        }

        // Seules les étapes actives d'un projet guidé actif comptent : une étape désactivée ne bloque plus le chapitre.
        $etape = EtapeProjetGuide::with('projetGuide')
            ->whereIn('chapitre_id', $chapitreIds)
            ->where('actif', true)
            ->whereHas('projetGuide', fn ($q) => $q->where('actif', true))
            ->first();

        if ($etape) {
            $projet = $etape->projetGuide?->titre ?? 'un projet guidé';

            throw new RegleMetierException(
                "{$sujet} est utilisé par une étape du projet guidé « {$projet} » : modifiez d’abord cette étape."
            );
        }
    }
}
