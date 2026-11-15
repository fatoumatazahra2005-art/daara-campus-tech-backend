<?php

namespace App\Http\Controllers\Api\Apprenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apprenant\Presenteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\TentativeQuiz;
use App\Services\Apprenant\QuizApprenantService;

class QuizApprenantController extends Controller
{
    public function __construct(private QuizApprenantService $quiz)
    {
    }

    public function demarrer(Request $request, Quiz $quiz): JsonResponse
    {
        $t = $this->quiz->demarrer($request->user(), $quiz);

        return response()->json(['data' => Presenteur::tentative($t) + [
            'duree_limite' => $quiz->duree_limite,
            'questions' => $this->quiz->questions($t),
        ]], 201);
    }

    public function tentative(Request $request, TentativeQuiz $tentative): JsonResponse
    {
        $this->posseder($request, $tentative);

        return response()->json(['data' => Presenteur::tentative($tentative) + ['questions' => $this->quiz->questions($tentative)]]);
    }

    public function repondre(Request $request, TentativeQuiz $tentative, Question $question): JsonResponse
    {
        $this->posseder($request, $tentative);

        $data = $request->validate([
            'propositions' => ['required', 'array', 'min:1'],
            'propositions.*' => ['integer'],
        ]);

        return response()->json(['data' => $this->quiz->repondre($tentative, $question, $data['propositions'])]);
    }

    public function terminer(Request $request, TentativeQuiz $tentative): JsonResponse
    {
        $this->posseder($request, $tentative);

        return response()->json(['data' => $this->formater($this->quiz->terminer($tentative))]);
    }

    public function resultat(Request $request, TentativeQuiz $tentative): JsonResponse
    {
        $this->posseder($request, $tentative);

        return response()->json(['data' => $this->formater($this->quiz->resultat($tentative))]);
    }

    public function historique(Request $request, Quiz $quiz): JsonResponse
    {
        return response()->json(['data' => $this->quiz->historique($request->user(), $quiz)->map(fn ($t) => Presenteur::tentative($t))->values()]);
    }

    private function formater(array $r): array
    {
        return Presenteur::tentative($r['tentative']) + [
            'seuil_reussite' => $r['seuil_reussite'],
            'corrections' => $r['corrections'],
        ];
    }

    private function posseder(Request $request, TentativeQuiz $tentative): void
    {
        abort_unless($tentative->apprenant_id === $request->user()->id, 404);
    }
}
