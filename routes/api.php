<?php

use App\Http\Controllers\Api\Administrateur\AbonnementController;
use App\Http\Controllers\Api\Administrateur\CoursController as AdminCoursController;
use App\Http\Controllers\Api\Administrateur\DashboardController;
use App\Http\Controllers\Api\Administrateur\ForumAdminController;
use App\Http\Controllers\Api\Administrateur\ModerationController;
use App\Http\Controllers\Api\Administrateur\RenduAdminController;
use App\Http\Controllers\Api\Administrateur\UtilisateurController;

use App\Http\Controllers\Api\Apprenant\AbonnementApprenantController;
use App\Http\Controllers\Api\Apprenant\ApprentissageController;
use App\Http\Controllers\Api\Apprenant\CatalogueApprenantController;
use App\Http\Controllers\Api\Apprenant\CertificatController;
use App\Http\Controllers\Api\Apprenant\InscriptionController;
use App\Http\Controllers\Api\Apprenant\PaiementCallbackController;
use App\Http\Controllers\Api\Apprenant\ProfilApprenantController;
use App\Http\Controllers\Api\Apprenant\ProgressionEtapeProjetGuideController;
use App\Http\Controllers\Api\Apprenant\ProjetGuideApprenantController;
use App\Http\Controllers\Api\Apprenant\QuizApprenantController;
use App\Http\Controllers\Api\Apprenant\RenduProjetController;
use App\Http\Controllers\Api\Apprenant\TableauDeBordController;
use App\Http\Controllers\Api\Apprenant\TelechargementController;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogueController;

use App\Http\Controllers\Api\CodeListController;
use App\Http\Controllers\Api\Encadreur\ActivationContenuController;
use App\Http\Controllers\Api\Encadreur\BlocController;
use App\Http\Controllers\Api\Encadreur\ChapitreController;
use App\Http\Controllers\Api\Encadreur\CorrectionController;
use App\Http\Controllers\Api\Encadreur\CoursController as EncadreurCoursController;
use App\Http\Controllers\Api\Encadreur\ModuleController;
use App\Http\Controllers\Api\Encadreur\ProjetGuideController;
use App\Http\Controllers\Api\Encadreur\PropositionController;
use App\Http\Controllers\Api\Encadreur\QuestionController;
use App\Http\Controllers\Api\Encadreur\QuizController;

use App\Http\Controllers\Api\Encadreur\SuiviApprenantsController;
use App\Http\Controllers\Api\FormuleAbonnementController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\Api\ParcoursController;
use App\Http\Controllers\Api\ParcoursCoursController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\ProjetController;
use App\Http\Controllers\Api\TechnologieController;

use Illuminate\Support\Facades\Route;


Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | ROUTES PUBLIQUES
    |--------------------------------------------------------------------------
    */

    // Authentification
    Route::post('auth/register', [AuthController::class, 'register']);

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/auth/web/login', [AuthController::class, 'connexionWeb'])->middleware('throttle:10,1');

    // Paiement : callback de l'opérateur
    Route::post('paiements/callback', PaiementCallbackController::class);

    // Vérification publique d'un certificat
    Route::get('certificats/verifier/{code}', [CertificatController::class, 'verifier']);


    /*
    |--------------------------------------------------------------------------
    | ROUTES AUTHENTIFIÉES
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        // Authentification
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        // Mon profil (administrateur et encadreur, espace web)
        Route::get('profil', [ProfilController::class, 'show']);
        Route::put('profil', [ProfilController::class, 'update']);
        Route::put('profil/mot-de-passe', [ProfilController::class, 'motDePasse'])->middleware('throttle:5,1');


        /*
        |--------------------------------------------------------------------------
        | CATALOGUE GÉNÉRAL
        |--------------------------------------------------------------------------
        */

        Route::get('cours', [CatalogueController::class, 'index']);
        Route::get('cours/{cours}', [CatalogueController::class, 'show']);


        /*
        |--------------------------------------------------------------------------
        | TECHNOLOGIES
        |--------------------------------------------------------------------------
        */

        Route::get('technologies', [TechnologieController::class, 'index']);

        Route::get('technologies/{technologie}', [
            TechnologieController::class,
            'show'
        ]);


        /*
        |--------------------------------------------------------------------------
        | ADMINISTRATEUR
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:ADMIN')
            ->prefix('admin')
            ->group(function () {

                // Tableau de bord
                Route::get('dashboard', [DashboardController::class, 'index']);


                // Gestion des technologies
                Route::post('technologies', [TechnologieController::class, 'store']);

                Route::put('technologies/{technologie}', [
                    TechnologieController::class,
                    'update'
                ]);

                Route::delete('technologies/{technologie}', [
                    TechnologieController::class,
                    'destroy'
                ]);


                // Gestion des abonnements
                Route::get('abonnements', [AbonnementController::class, 'index']);

                Route::get('abonnements/{id}', [
                    AbonnementController::class,
                    'show'
                ]);


                // Gestion des utilisateurs
                Route::get('utilisateurs', [UtilisateurController::class, 'index']);

                Route::post('utilisateurs', [UtilisateurController::class, 'store']);

                Route::get('utilisateurs/{id}', [
                    UtilisateurController::class,
                    'show'
                ])->whereNumber('id');

                Route::put('utilisateurs/{utilisateur}', [
                    UtilisateurController::class,
                    'update'
                ]);

                Route::delete('utilisateurs/{utilisateur}', [
                    UtilisateurController::class,
                    'destroy'
                ]);

                Route::post('utilisateurs/{utilisateur}/bloquer', [
                    UtilisateurController::class,
                    'bloquer'
                ]);

                Route::post('utilisateurs/{utilisateur}/desactiver', [
                    UtilisateurController::class,
                    'desactiver'
                ]);

                Route::post('utilisateurs/{utilisateur}/activer', [
                    UtilisateurController::class,
                    'activer'
                ]);

                Route::patch('utilisateurs/{id}/reinitialiser-mot-de-passe', [
                    UtilisateurController::class,
                    'reinitialiserMotDePasse'
                ]);

                Route::patch('utilisateurs/{id}/niveau', [
                    UtilisateurController::class,
                    'modifierNiveau'
                ]);


                // Validation et gestion des cours
                Route::get('cours', [AdminCoursController::class, 'index']);

                Route::get('cours/{cours}', [
                    AdminCoursController::class,
                    'show'
                ]);

                Route::post('cours/{cours}/valider', [
                    AdminCoursController::class,
                    'valider'
                ]);

                Route::post('cours/{cours}/refuser', [
                    AdminCoursController::class,
                    'refuser'
                ]);

                Route::post('cours/{cours}/archiver', [
                    AdminCoursController::class,
                    'archiver'
                ]);

                // Cours publié modifié par son encadreur : « Marquer comme relu »
                Route::post('cours/{cours}/relu', [
                    AdminCoursController::class,
                    'marquerRelu'
                ]);

                // Référentiel CodeList (listes de valeurs)
                Route::get('codelists/types', [CodeListController::class, 'types']);
                Route::get('codelists', [CodeListController::class, 'index']);
                Route::post('codelists', [CodeListController::class, 'store']);
                Route::put('codelists/{codelist}', [CodeListController::class, 'update']);
                Route::patch('codelists/{codelist}/desactiver', [CodeListController::class, 'desactiver']);
                Route::patch('codelists/{codelist}/activer', [CodeListController::class, 'activer']);
                Route::delete('codelists/{codelist}', [CodeListController::class, 'destroy']);


                // Formules d'abonnement
                Route::apiResource(
                    'formules-abonnement',
                    FormuleAbonnementController::class
                )->parameters([
                    'formules-abonnement' => 'formule'
                ]);


                // Gestion des parcours
                // parameters() : sans cela, Laravel nomme le paramètre {parcour} et la liaison
                // automatique avec Parcours $parcours ne fonctionne pas.
                Route::apiResource('parcours', ParcoursController::class)
                    ->parameters(['parcours' => 'parcours']);

                // Gestion des cours d'un parcours
                Route::prefix('parcours/{parcours}/cours')
                    ->controller(ParcoursCoursController::class)
                    ->group(function () {

                        Route::post('/', 'ajouter');

                        Route::patch('/{cours}', 'modifier');

                        Route::delete('/{cours}', 'retirer');

                    });

                Route::patch(
                    'parcours/{parcours}/publier',
                    [ParcoursController::class, 'publier']
                );

                Route::patch(
                    'parcours/{parcours}/archiver',
                    [ParcoursController::class, 'archiver']
                );


                // Gestion des projets
                Route::get('projets', [ProjetController::class, 'index']);

                Route::post('parcours/{parcours}/projets', [
                    ProjetController::class,
                    'store'
                ]);

                Route::get('projets/{projet}', [
                    ProjetController::class,
                    'show'
                ]);

                Route::put('projets/{projet}', [
                    ProjetController::class,
                    'update'
                ]);

                Route::delete('projets/{projet}', [
                    ProjetController::class,
                    'destroy'
                ]);

                Route::patch('projets/{projet}/activer', [ProjetController::class, 'activer']);
                Route::patch('projets/{projet}/desactiver', [ProjetController::class, 'desactiver']);


                // Modération du forum
                Route::get('signalements', [
                    ModerationController::class,
                    'signalements'
                ]);

                Route::get('signalements/{id}', [
                    ModerationController::class,
                    'detailsSignalement'
                ]);

                Route::patch('signalements/{id}/traiter', [
                    ModerationController::class,
                    'traiterSignalement'
                ]);

                Route::get('messages-masques', [
                    ModerationController::class,
                    'messagesMasques'
                ]);

                Route::patch('messages/{messageId}/masquer', [
                    ModerationController::class,
                    'masquerMessage'
                ]);

                Route::patch('messages/{messageId}/demasquer', [
                    ModerationController::class,
                    'demasquerMessage'
                ]);

                // Consultation du forum (messages masqués compris)
                Route::get('forum/sujets', [ForumAdminController::class, 'sujets']);
                Route::get('forum/sujets/{sujet}', [ForumAdminController::class, 'sujetDetail']);

                // Rendus de projet (RG35)
                Route::get('rendus', [RenduAdminController::class, 'index']);
                Route::get('rendus/{rendu}', [RenduAdminController::class, 'show'])->whereNumber('rendu');
                Route::get('rendus/{rendu}/suggestions', [RenduAdminController::class, 'suggestions'])->whereNumber('rendu');
                Route::patch('rendus/{rendu}/affecter', [RenduAdminController::class, 'affecter'])->whereNumber('rendu');

            });


        /*
        |--------------------------------------------------------------------------
        | ENCADREUR
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:ENCADREUR')
            ->prefix('encadreur')
            ->group(function () {

                Route::get('profil', [ProfilController::class, 'encadreur']);


                // Gestion des cours
                Route::get('cours', [EncadreurCoursController::class, 'index']);

                Route::post('cours', [EncadreurCoursController::class, 'store']);

                Route::get('cours/{cours}', [
                    EncadreurCoursController::class,
                    'show'
                ]);

                Route::put('cours/{cours}', [
                    EncadreurCoursController::class,
                    'update'
                ]);

                Route::delete('cours/{cours}', [
                    EncadreurCoursController::class,
                    'destroy'
                ]);

                Route::post('cours/{cours}/soumettre', [
                    EncadreurCoursController::class,
                    'soumettre'
                ]);

                Route::post('cours/{cours}/archiver', [
                    EncadreurCoursController::class,
                    'archiver'
                ]);


                // Modules, chapitres et blocs
                Route::post('cours/{cours}/modules', [
                    ModuleController::class,
                    'store'
                ]);

                Route::put('modules/{module}', [
                    ModuleController::class,
                    'update'
                ]);

                Route::delete('modules/{module}', [
                    ModuleController::class,
                    'destroy'
                ]);

                Route::post('modules/{module}/chapitres', [
                    ChapitreController::class,
                    'store'
                ]);

                Route::put('chapitres/{chapitre}', [
                    ChapitreController::class,
                    'update'
                ]);

                Route::delete('chapitres/{chapitre}', [
                    ChapitreController::class,
                    'destroy'
                ]);

                Route::post('chapitres/{chapitre}/blocs', [
                    BlocController::class,
                    'store'
                ]);

                Route::put('blocs/{bloc}', [
                    BlocController::class,
                    'update'
                ]);

                Route::delete('blocs/{bloc}', [
                    BlocController::class,
                    'destroy'
                ]);


                // Quiz, questions et propositions
                Route::get('chapitres/{chapitre}/quiz', [
                    QuizController::class,
                    'show'
                ]);

                Route::post('chapitres/{chapitre}/quiz', [
                    QuizController::class,
                    'store'
                ]);

                Route::put('quiz/{quiz}', [
                    QuizController::class,
                    'update'
                ]);

                Route::delete('quiz/{quiz}', [
                    QuizController::class,
                    'destroy'
                ]);

                Route::post('quiz/{quiz}/questions', [
                    QuestionController::class,
                    'store'
                ]);

                Route::put('questions/{question}', [
                    QuestionController::class,
                    'update'
                ]);

                Route::delete('questions/{question}', [
                    QuestionController::class,
                    'destroy'
                ]);

                Route::post('questions/{question}/propositions', [
                    PropositionController::class,
                    'store'
                ]);

                Route::put('propositions/{proposition}', [
                    PropositionController::class,
                    'update'
                ]);

                Route::delete('propositions/{proposition}', [
                    PropositionController::class,
                    'destroy'
                ]);


                // Projets guidés et étapes
                Route::post('cours/{cours}/projets-guides', [
                    ProjetGuideController::class,
                    'store'
                ]);

                Route::put('projets-guides/{projetGuide}', [
                    ProjetGuideController::class,
                    'update'
                ]);

                Route::delete('projets-guides/{projetGuide}', [
                    ProjetGuideController::class,
                    'destroy'
                ]);

                Route::post('projets-guides/{projetGuide}/etapes', [
                    ProjetGuideController::class,
                    'storeEtape'
                ]);

                Route::put('etapes/{etape}', [
                    ProjetGuideController::class,
                    'updateEtape'
                ]);

                Route::delete('etapes/{etape}', [
                    ProjetGuideController::class,
                    'destroyEtape'
                ]);

                // Activation / désactivation du contenu (suppression logique)
                Route::patch('modules/{module}/activer', [ActivationContenuController::class, 'activerModule']);
                Route::patch('modules/{module}/desactiver', [ActivationContenuController::class, 'desactiverModule']);
                Route::patch('chapitres/{chapitre}/activer', [ActivationContenuController::class, 'activerChapitre']);
                Route::patch('chapitres/{chapitre}/desactiver', [ActivationContenuController::class, 'desactiverChapitre']);
                Route::patch('quiz/{quiz}/activer', [ActivationContenuController::class, 'activerQuiz']);
                Route::patch('quiz/{quiz}/desactiver', [ActivationContenuController::class, 'desactiverQuiz']);
                Route::patch('questions/{question}/activer', [ActivationContenuController::class, 'activerQuestion']);
                Route::patch('questions/{question}/desactiver', [ActivationContenuController::class, 'desactiverQuestion']);
                Route::patch('propositions/{proposition}/activer', [ActivationContenuController::class, 'activerProposition']);
                Route::patch('propositions/{proposition}/desactiver', [ActivationContenuController::class, 'desactiverProposition']);


                // Correction des rendus de projet (RG35)
                Route::get('corrections', [CorrectionController::class, 'index']);
                Route::get('corrections/{rendu}', [CorrectionController::class, 'show'])->whereNumber('rendu');
                Route::post('corrections/{rendu}/corriger', [CorrectionController::class, 'corriger'])->whereNumber('rendu');

                // Suivi des apprenants inscrits à mes cours
                Route::get('apprenants', [SuiviApprenantsController::class, 'index']);
                Route::get('inscriptions/{inscription}', [SuiviApprenantsController::class, 'show'])->whereNumber('inscription');

            });


        /*
        |--------------------------------------------------------------------------
        | FORUM
        |--------------------------------------------------------------------------
        | Réservé aux abonnés : un apprenant doit avoir un abonnement qui donne accès
        | (ESSAI, ACTIF, RESILIE dans sa période). Encadreurs et administrateurs : toujours.
        */
        /*
        |--------------------------------------------------------------------------
        | FORUM
        |--------------------------------------------------------------------------
        | Réservé aux abonnés : un apprenant doit avoir un abonnement qui donne accès
        | (ESSAI, ACTIF, RESILIE dans sa période). Encadreurs et administrateurs : toujours.
        */

        Route::middleware('acces.forum')->prefix('forum')->group(function () {

            Route::get('sujets', [ForumController::class, 'sujets']);

            Route::get('sujets/{sujet}', [ForumController::class, 'sujet']);

            Route::middleware('throttle:20,1')->group(function () {

                Route::post('sujets', [ForumController::class, 'creerSujet']);

                Route::post('sujets/{sujet}/messages', [ForumController::class, 'repondre']);

                Route::post('sujets/{sujet}/resoudre', [ForumController::class, 'resoudre']);

                // 👍 en interrupteur : POST = voter, DELETE = retirer son vote
                Route::post('messages/{message}/vote', [ForumController::class, 'voter']);

                Route::delete('messages/{message}/vote', [ForumController::class, 'retirerVote']);

                Route::post('messages/{message}/signalement', [ForumController::class, 'signaler']);

            });

        });


        /*
        |--------------------------------------------------------------------------
        | APPRENANT
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:APPRENANT')
            ->prefix('apprenant')
            ->group(function () {

                // Profil et tableau de bord
                Route::get('tableau-de-bord', TableauDeBordController::class);

                Route::get('profil', [
                    ProfilApprenantController::class,
                    'show'
                ]);

                Route::put('profil', [
                    ProfilApprenantController::class,
                    'update'
                ]);

                Route::delete('compte', [
                    ProfilApprenantController::class,
                    'destroy'
                ]);


                // Catalogue
                Route::get('catalogue', [
                    CatalogueApprenantController::class,
                    'index'
                ]);

                Route::get('catalogue/{cours}', [
                    CatalogueApprenantController::class,
                    'show'
                ]);

                Route::get('parcours', [
                    InscriptionController::class,
                    'catalogueParcours'
                ]);


                // Abonnements et paiements
                Route::get('formules', [
                    AbonnementApprenantController::class,
                    'formules'
                ]);

                Route::get('abonnement', [
                    AbonnementApprenantController::class,
                    'courant'
                ]);

                Route::post('abonnement', [
                    AbonnementApprenantController::class,
                    'souscrire'
                ]);

                Route::post('abonnement/payer', [
                    AbonnementApprenantController::class,
                    'payer'
                ]);

                Route::post('abonnement/resilier', [
                    AbonnementApprenantController::class,
                    'resilier'
                ]);

                Route::get('paiements', [
                    AbonnementApprenantController::class,
                    'paiements'
                ]);

                Route::post('paiements/{paiement}/relancer', [
                    AbonnementApprenantController::class,
                    'relancer'
                ])->whereNumber('paiement');


                // Inscriptions et progression
                Route::get('inscriptions', [
                    InscriptionController::class,
                    'index'
                ]);

                Route::post('cours/{cours}/inscription', [
                    InscriptionController::class,
                    'sInscrire'
                ]);

                Route::post('inscriptions/{inscription}/abandonner', [
                    InscriptionController::class,
                    'abandonner'
                ]);

                Route::post('inscriptions/{inscription}/reprendre', [
                    InscriptionController::class,
                    'reprendre'
                ]);

                Route::post('parcours/{parcours}/inscription', [
                    InscriptionController::class,
                    'parcoursInscription'
                ]);

                Route::post('inscriptions-parcours/{inscriptionParcours}/abandonner', [
                    InscriptionController::class,
                    'abandonnerParcours'
                ]);
                Route::post(
                    '/inscriptions-parcours/{inscriptionParcours}/reprendre',
                    [InscriptionController::class, 'reprendreParcours']
                );


                // Lecture du contenu
                Route::get('cours/{cours}/contenu', [
                    ApprentissageController::class,
                    'structure'
                ]);

                Route::get('chapitres/{chapitre}', [
                    ApprentissageController::class,
                    'chapitre'
                ]);

                Route::post('chapitres/{chapitre}/terminer', [
                    ApprentissageController::class,
                    'terminer'
                ]);


                // Projets guidés
                Route::get('cours/{cours}/projets-guides', [
                    ProjetGuideApprenantController::class,
                    'index'
                ]);

                Route::post('etapes-projet-guide/{etape}/terminer', [
                    ProgressionEtapeProjetGuideController::class,
                    'terminer'
                ]);


                // Quiz
                Route::get('quiz/{quiz}/tentatives', [
                    QuizApprenantController::class,
                    'historique'
                ]);

                Route::post('quiz/{quiz}/demarrer', [
                    QuizApprenantController::class,
                    'demarrer'
                ]);

                Route::get('tentatives/{tentative}', [
                    QuizApprenantController::class,
                    'tentative'
                ]);

                Route::post('tentatives/{tentative}/questions/{question}/reponse', [
                    QuizApprenantController::class,
                    'repondre'
                ]);

                Route::post('tentatives/{tentative}/terminer', [
                    QuizApprenantController::class,
                    'terminer'
                ]);

                Route::get('tentatives/{tentative}/resultat', [
                    QuizApprenantController::class,
                    'resultat'
                ]);


                // Certificats
                Route::get('certificats', [CertificatController::class, 'index']);
                Route::get('certificats/{certificat}/pdf', [CertificatController::class, 'pdf']);


                // Téléchargements hors ligne
                Route::get('telechargements', [
                    TelechargementController::class,
                    'index'
                ]);

                Route::post('telechargements/verifier', [
                    TelechargementController::class,
                    'verifier'
                ]);

                Route::post('chapitres/{chapitre}/telechargement', [
                    TelechargementController::class,
                    'store'
                ]);


                // Rendus de projets
                Route::get('rendus', [
                    RenduProjetController::class,
                    'index'
                ]);

                Route::post('rendus/{rendu}/soumettre', [
                    RenduProjetController::class,
                    'soumettre'
                ]);

                // Profil et tableau de bord
                Route::get('tableau-de-bord', TableauDeBordController::class);

                Route::get('profil', [ProfilApprenantController::class, 'show']);

                Route::put('profil', [ProfilApprenantController::class, 'update']);

                Route::put('mot-de-passe', [ProfilApprenantController::class, 'motDePasse'])
                    ->middleware('throttle:5,1');

                Route::delete('compte', [ProfilApprenantController::class, 'destroy'])
                    ->middleware('throttle:5,1');

            });

    });

});
