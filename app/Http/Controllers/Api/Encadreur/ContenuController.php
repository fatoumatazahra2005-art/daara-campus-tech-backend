<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Services\CoursService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Base des contrôleurs de contenu : toute modification passe par le droit « update » sur le cours parent. */
abstract class ContenuController extends Controller
{
    public function __construct(protected readonly CoursService $coursService)
    {
    }

    /** Vérifie que l'utilisateur peut modifier le cours auquel appartient $element. */
    protected function autoriserSur(Model $element): Cours
    {
        $cours = $this->coursService->coursDe($element);
        Gate::authorize('update', $cours);

        return $cours;
    }
}
