<?php

namespace App\Services;

use App\Models\BlocCode;
use App\Models\BlocContenu;
use App\Models\BlocImage;
use App\Models\BlocTexte;
use App\Models\Chapitre;
use App\Models\Cours;
use App\Models\Module;
use App\Services\Apprenant\ProgressionService;
use Illuminate\Support\Facades\DB;

/**
 * Contenu pédagogique : modules, chapitres et blocs (TEXTE, IMAGE, CODE).
 * La position (ordre) d'un nouvel élément est toujours la suivante dans son parent.
 *
 * Règle 3 : une fois le cours publié, un module ou un chapitre retiré est désactivé (actif = false),
 * avec les mêmes contrôles que l'interrupteur (ActivationContenuService).
 * Règle 4 : un chapitre ajouté ou retiré change ce qu'il faut terminer : les inscriptions EN_COURS
 * du cours sont recalculées ; les inscriptions TERMINEE restent figées.
 */
class ContenuCoursService
{
    public function __construct(
        private readonly CoursService $cours,
        private readonly ActivationContenuService $activation,
        private readonly ProgressionService $progression,
    ) {
    }

    // ------------------------------------------------------------------ Modules
    public function ajouterModule(Cours $cours, array $donnees): Module
    {
        return DB::transaction(function () use ($cours, $donnees) {
            $module = Module::create([
                'titre' => $donnees['titre'],
                'description' => $donnees['description'] ?? null,
                'ordre' => (int) Module::where('cours_id', $cours->id)->max('ordre') + 1,
                'cours_id' => $cours->id,
            ]);

            $this->cours->marquerModifie($cours);

            return $module->refresh();
        });
    }

    public function modifierModule(Module $module, array $donnees): Module
    {
        return DB::transaction(function () use ($module, $donnees) {
            $module->update(array_intersect_key($donnees, array_flip(['titre', 'description'])));
            $this->cours->marquerModifie($this->cours->coursDe($module));

            return $module->refresh();
        });
    }

    /**
     * Cours jamais publié : suppression réelle.
     * Cours déjà publié : désactivation, avec les contrôles de l'interrupteur et le recalcul des inscrits.
     */
    public function retirerModule(Module $module): void
    {
        $cours = $this->cours->coursDe($module);

        if ($cours->date_publication !== null) {
            $this->activation->desactiver($module);

            return;
        }

        DB::transaction(function () use ($module, $cours) {
            $this->cours->retirer($module, $cours);
        });
    }

    // ------------------------------------------------------------------ Chapitres
    public function ajouterChapitre(Module $module, array $donnees): Chapitre
    {
        $chapitre = DB::transaction(function () use ($module, $donnees) {
            $chapitre = Chapitre::create([
                'titre' => $donnees['titre'],
                'objectif' => $donnees['objectif'] ?? null,
                'prerequis' => $donnees['prerequis'] ?? null,
                'duree_estimee' => $donnees['duree_estimee'] ?? null,
                'xp_recompense' => $donnees['xp_recompense'] ?? 10,
                'ordre' => (int) Chapitre::where('module_id', $module->id)->max('ordre') + 1,
                'module_id' => $module->id,
            ]);

            $this->cours->marquerModifie($this->cours->coursDe($module));

            return $chapitre->refresh();
        });

        // Règle 4 : un nouveau chapitre actif est à faire pour les inscrits EN_COURS (leur pourcentage baisse).
        if ($chapitre->actif && $module->actif) {
            $this->progression->recalculerCours($module->cours_id);
        }

        return $chapitre;
    }

    public function modifierChapitre(Chapitre $chapitre, array $donnees): Chapitre
    {
        return DB::transaction(function () use ($chapitre, $donnees) {
            $chapitre->update(array_intersect_key($donnees, array_flip(['titre', 'objectif', 'prerequis', 'duree_estimee', 'xp_recompense'])));
            $this->cours->marquerModifie($this->cours->coursDe($chapitre));

            return $chapitre->refresh();
        });
    }

    /**
     * Cours jamais publié : suppression réelle.
     * Cours déjà publié : désactivation, avec les contrôles de l'interrupteur et le recalcul des inscrits.
     */
    public function retirerChapitre(Chapitre $chapitre): void
    {
        $cours = $this->cours->coursDe($chapitre);

        if ($cours->date_publication !== null) {
            $this->activation->desactiver($chapitre);

            return;
        }

        DB::transaction(function () use ($chapitre, $cours) {
            $this->cours->retirer($chapitre, $cours);
        });
    }

    // ------------------------------------------------------------------ Blocs
    /** @param array{type_bloc: string, contenu?: string, url_image?: string, alt?: ?string, code?: string, langage?: string} $donnees */
    public function ajouterBloc(Chapitre $chapitre, array $donnees): BlocContenu
    {
        return DB::transaction(function () use ($chapitre, $donnees) {
            $bloc = BlocContenu::create([
                'type_bloc' => $donnees['type_bloc'],
                'ordre' => (int) BlocContenu::where('chapitre_id', $chapitre->id)->max('ordre') + 1,
                'chapitre_id' => $chapitre->id,
            ]);

            match ($donnees['type_bloc']) {
                'TEXTE' => BlocTexte::create(['id' => $bloc->id, 'contenu' => $donnees['contenu']]),
                'IMAGE' => BlocImage::create(['id' => $bloc->id, 'url_image' => $donnees['url_image'], 'alt' => $donnees['alt'] ?? null]),
                'CODE' => BlocCode::create(['id' => $bloc->id, 'code' => $donnees['code'], 'langage' => $donnees['langage']]),
            };

            $this->cours->marquerModifie($this->cours->coursDe($chapitre));

            return $bloc->refresh()->load(['texte', 'image', 'code']);
        });
    }

    /** Le type d'un bloc ne change pas ; seuls ses champs se modifient. */
    public function modifierBloc(BlocContenu $bloc, array $donnees): BlocContenu
    {
        return DB::transaction(function () use ($bloc, $donnees) {
            match ($bloc->type_bloc) {
                'TEXTE' => $bloc->texte->update(array_intersect_key($donnees, array_flip(['contenu']))),
                'IMAGE' => $bloc->image->update(array_intersect_key($donnees, array_flip(['url_image', 'alt']))),
                'CODE' => $bloc->code->update(array_intersect_key($donnees, array_flip(['code', 'langage']))),
            };

            $this->cours->marquerModifie($this->cours->coursDe($bloc));

            return $bloc->refresh()->load(['texte', 'image', 'code']);
        });
    }

    /** Un bloc n'a pas de suppression logique : aucune progression ne lui est liée. */
    public function retirerBloc(BlocContenu $bloc): void
    {
        DB::transaction(function () use ($bloc) {
            $cours = $this->cours->coursDe($bloc);
            $bloc->delete();
            $this->cours->marquerModifie($cours);
        });
    }
}
