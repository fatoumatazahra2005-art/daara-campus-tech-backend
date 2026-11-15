<?php

namespace App\Http\Controllers\Api\Administrateur;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangerStatutRequest;
use App\Http\Requests\StoreUtilisateurRequest;
use App\Http\Requests\UpdateUtilisateurRequest;
use App\Models\Utilisateur;
use App\Services\UtilisateurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UtilisateurController extends Controller
{
    public function __construct(private UtilisateurService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Utilisateur::class);

        $filtres = $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'role'      => ['nullable', 'string'],
            'statut'    => ['nullable', 'string'],
            'par_page'  => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->service->lister($filtres, (int) ($filtres['par_page'] ?? 10));
    }

    public function store(StoreUtilisateurRequest $request): JsonResponse
    {
        Gate::authorize('create', Utilisateur::class);

        $utilisateur = $this->service->creer($request->validated());

        return response()->json($utilisateur, 201);
    }

    public function show(int $id)
    {
        Gate::authorize('viewAny', Utilisateur::class);

        return $this->service->consulter($id);
    }

    public function update(UpdateUtilisateurRequest $request, Utilisateur $utilisateur)
    {
        Gate::authorize('update', $utilisateur);

        return $this->service->modifier($utilisateur, $request->validated());
    }

    public function bloquer(Request $request, Utilisateur $utilisateur)
    {
        return $this->changerStatut($request, $utilisateur, 'bloquer');
    }

    public function desactiver(Request $request, Utilisateur $utilisateur)
    {
        return $this->changerStatut($request, $utilisateur, 'desactiver');
    }

    public function activer(Request $request, Utilisateur $utilisateur)
    {
        return $this->changerStatut($request, $utilisateur, 'activer');
    }

    private function changerStatut(Request $request, Utilisateur $cible, string $action)
    {
        Gate::authorize('changerStatut', $cible);

        return $this->service->changerStatut($cible, $request->user(), $action);
    }

    /**
     * Modifier le niveau pédagogique d'un apprenant.
     */
    public function modifierNiveau(Request $request, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Utilisateur::class);

        $donnees = $request->validate([
            'niveau' => [
                'required',
                'string',
                'in:DEBUTANT,INTERMEDIAIRE,AVANCE',
            ],
        ]);

        $utilisateur = $this->service->modifierNiveau(
            $id,
            $donnees['niveau']
        );

        return response()->json([
            'message' => 'Niveau de l’apprenant modifié avec succès.',
            'data' => $utilisateur,
        ]);
    }
    /**
     * Réinitialiser le mot de passe d'un utilisateur.
     */
    public function reinitialiserMotDePasse(
        Request $request,
        int $id
    ): JsonResponse {

        Gate::authorize('viewAny', Utilisateur::class);

        $donnees = $request->validate([
            'nouveau_mot_de_passe' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $this->service->reinitialiserMotDePasse(
            $id,
            $donnees['nouveau_mot_de_passe']
        );

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès. L’utilisateur doit se reconnecter.',
        ]);
    }

    public function destroy(Utilisateur $utilisateur): JsonResponse
    {
        Gate::authorize('delete', $utilisateur);

        $this->service->supprimer($utilisateur);

        return response()->json([
            'message' => 'Utilisateur supprimé avec succès.'
        ]);
    }
}
