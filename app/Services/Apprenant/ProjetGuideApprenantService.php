<?php

namespace App\Services\Apprenant;

use App\Exceptions\RegleMetierException;
use App\Models\Chapitre;
use App\Models\Cours;
use App\Models\InscriptionCours;
use App\Models\ProgressionChapitre;
use App\Models\Utilisateur;

/** Lecture des projets guidés (fil rouge) d'un cours : réservée aux inscrits ayant un abonnement donnant accès. */
class ProjetGuideApprenantService
{
    public function __construct(private AccesService $acces)
    {
    }

    /** @return list<array<string,mixed>> */
    public function duCours(Utilisateur $utilisateur, Cours $cours): array
    {
        $this->acces->exigerCoursAccessible($utilisateur->id, $cours);
        $abonnement = $this->acces->exigerAbonnementActif($utilisateur->id);

        $inscription = InscriptionCours::where('apprenant_id', $utilisateur->id)->where('cours_id', $cours->id)->first()
            ?? throw new RegleMetierException('Inscrivez-vous d’abord à ce cours.', 422);

        $termines = ProgressionChapitre::where('inscription_cours_id', $inscription->id)
            ->where('est_termine', true)->pluck('chapitre_id')->map(fn ($i) => (int) $i)->all();

        $rangs = $this->acces->chapitreIdsActifs($cours->id)->map(fn ($i) => (int) $i)->values()->flip();
        $limite = $this->acces->limiteEssai($abonnement);
        $titres = Chapitre::whereIn('id', $rangs->keys())->pluck('titre', 'id');

        return $cours->projetsGuides()->with(['etapes' => fn ($q) => $q->orderBy('ordre')])->orderBy('id')->get()
            ->map(fn ($projet) => [
                'id' => $projet->id,
                'titre' => $projet->titre,
                'description' => $projet->description,
                'image_resultat' => $projet->image_resultat,
                'etapes' => $projet->etapes->map(function ($e) use ($rangs, $limite, $termines, $titres) {
                    $rang = $rangs->get((int) $e->chapitre_id);

                    return [
                        'id' => $e->id,
                        'ordre' => (int) $e->ordre,
                        'consigne' => $e->consigne,
                        'code_depart' => $e->code_depart,
                        'tests_attendus' => $e->tests_attendus,
                        'chapitre' => ['id' => $e->chapitre_id, 'titre' => $titres->get($e->chapitre_id)],
                        'chapitre_termine' => in_array((int) $e->chapitre_id, $termines, true),
                        // pendant l'essai, une étape liée à un chapitre hors limite reste verrouillée
                        'verrouille' => $limite !== null && ($rang === null || $rang >= $limite),
                    ];
                })->values()->all(),
            ])->values()->all();
    }
}
