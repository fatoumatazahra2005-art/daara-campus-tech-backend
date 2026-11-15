<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Models\Chapitre;
use App\Models\Module;
use App\Models\Proposition;
use App\Models\Question;
use App\Models\Quiz;
use App\Services\ActivationContenuService;
use App\Services\CoursService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/** Activation / désactivation du contenu d'un cours par son encadreur. */
class ActivationContenuController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly ActivationContenuService $activation)
    {
        parent::__construct($coursService);
    }

    public function activerModule(Module $module): JsonResponse { return $this->changer($module, true); }
    public function desactiverModule(Module $module): JsonResponse { return $this->changer($module, false); }

    public function activerChapitre(Chapitre $chapitre): JsonResponse { return $this->changer($chapitre, true); }
    public function desactiverChapitre(Chapitre $chapitre): JsonResponse { return $this->changer($chapitre, false); }

    public function activerQuiz(Quiz $quiz): JsonResponse { return $this->changer($quiz, true); }
    public function desactiverQuiz(Quiz $quiz): JsonResponse { return $this->changer($quiz, false); }

    public function activerQuestion(Question $question): JsonResponse { return $this->changer($question, true); }
    public function desactiverQuestion(Question $question): JsonResponse { return $this->changer($question, false); }

    public function activerProposition(Proposition $proposition): JsonResponse { return $this->changer($proposition, true); }
    public function desactiverProposition(Proposition $proposition): JsonResponse { return $this->changer($proposition, false); }

    private function changer(Model $element, bool $actif): JsonResponse
    {
        $this->autoriserSur($element);

        $element = $actif ? $this->activation->activer($element) : $this->activation->desactiver($element);

        return response()->json([
            'data' => [
                'id' => $element->id,
                'actif' => (bool) $element->actif,
            ],
            'message' => $actif ? 'Élément activé.' : 'Élément désactivé : il n’est plus visible par les apprenants.',
        ]);
    }
}
