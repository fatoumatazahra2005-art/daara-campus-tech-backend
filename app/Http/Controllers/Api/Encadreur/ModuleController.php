<?php

namespace App\Http\Controllers\Api\Encadreur;

use App\Http\Requests\Cours\ModuleRequest;
use App\Http\Resources\ModuleResource;
use App\Models\Cours;
use App\Models\Module;
use App\Services\ContenuCoursService;
use App\Services\CoursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ModuleController extends ContenuController
{
    public function __construct(CoursService $coursService, private readonly ContenuCoursService $contenu)
    {
        parent::__construct($coursService);
    }

    public function store(ModuleRequest $request, Cours $cours): JsonResponse
    {
        Gate::authorize('update', $cours);

        $module = $this->contenu->ajouterModule($cours, $request->validated());

        return (new ModuleResource($module))->response()->setStatusCode(201);
    }

    public function update(ModuleRequest $request, Module $module): ModuleResource
    {
        $this->autoriserSur($module);

        return new ModuleResource($this->contenu->modifierModule($module, $request->validated()));
    }

    public function destroy(Module $module): Response
    {
        $this->autoriserSur($module);

        $this->contenu->retirerModule($module);

        return response()->noContent();
    }
}
