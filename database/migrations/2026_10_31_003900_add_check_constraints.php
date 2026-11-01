<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contraintes CHECK du dictionnaire de données (bornes, positivité, cohérence des dates).
 *
 * Nécessite MySQL 8.0.19+, MariaDB 10.2+ ou PostgreSQL.
 * SQLite ne sait pas ajouter une contrainte après coup : la migration est ignorée,
 * ces règles restent alors garanties par l'application.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => [nom_contrainte => expression] */
    private array $checks = [
        'apprenants' => [
            'chk_apprenants_xp_total' => 'xp_total >= 0',
        ],
        'cours' => [
            'chk_cours_date_publication' => 'date_publication IS NULL OR date_publication >= date_creation',
        ],
        'parcours_cours' => [
            'chk_parcours_cours_ordre' => 'ordre >= 1',
        ],
        'modules' => [
            'chk_modules_ordre' => 'ordre >= 1',
        ],
        'chapitres' => [
            'chk_chapitres_ordre' => 'ordre >= 1',
            'chk_chapitres_duree' => 'duree_estimee IS NULL OR duree_estimee >= 0',
            'chk_chapitres_xp' => 'xp_recompense >= 0',
        ],
        'blocs_contenu' => [
            'chk_blocs_contenu_ordre' => 'ordre >= 1',
        ],
        'quizzes' => [
            'chk_quizzes_duree' => 'duree_limite IS NULL OR duree_limite >= 1',
            'chk_quizzes_seuil' => 'seuil_reussite BETWEEN 0 AND 100',
            'chk_quizzes_xp_bonus' => 'xp_bonus >= 0',
        ],
        'questions' => [
            'chk_questions_points' => 'points >= 1',
            'chk_questions_ordre' => 'ordre >= 1',
        ],
        'propositions' => [
            'chk_propositions_ordre' => 'ordre >= 1',
        ],
        'tentatives_quiz' => [
            'chk_tentatives_dates' => 'date_fin IS NULL OR date_fin >= date_debut',
            'chk_tentatives_score' => 'score IS NULL OR score BETWEEN 0 AND 100',
        ],
        'inscriptions_cours' => [
            'chk_inscriptions_cours_progression' => 'progression BETWEEN 0 AND 100',
        ],
        'inscriptions_parcours' => [
            'chk_inscriptions_parcours_progression' => 'progression BETWEEN 0 AND 100',
        ],
        'progressions_chapitre' => [
            'chk_progressions_dates' => 'date_fin IS NULL OR date_fin >= date_debut',
            // est_termine = true si et seulement si date_fin est renseignée
            'chk_progressions_termine' => '(est_termine AND date_fin IS NOT NULL) OR (NOT est_termine AND date_fin IS NULL)',
        ],
        'formules_abonnement' => [
            'chk_formules_prix' => 'prix > 0',
            'chk_formules_echeances' => 'nb_echeances_max BETWEEN 1 AND 3',
            'chk_formules_essai' => 'jours_essai >= 0',
            'chk_formules_limite_essai' => 'limite_chapitres_essai IS NULL OR limite_chapitres_essai >= 1',
            // limite obligatoire dès que joursEssai > 0
            'chk_formules_essai_limite' => 'jours_essai = 0 OR limite_chapitres_essai IS NOT NULL',
        ],
        'abonnements' => [
            'chk_abonnements_credits' => 'credits_hors_ligne >= 0',
        ],
        'periodes_abonnement' => [
            'chk_periodes_numero' => 'numero >= 1',
            'chk_periodes_dates' => 'date_fin > date_debut',
            'chk_periodes_prix' => 'prix_paye >= 0',
            'chk_periodes_echeances' => 'nb_echeances >= 1',
        ],
        'paiements' => [
            'chk_paiements_montant' => 'montant > 0',
            'chk_paiements_numero' => 'numero_echeance >= 1',
        ],
        'projets' => [
            'chk_projets_delai' => 'delai_jours >= 1',
            'chk_projets_note_min' => 'note_minimale BETWEEN 0 AND 20',
        ],
        'rendus_projet' => [
            'chk_rendus_note' => 'note IS NULL OR note BETWEEN 0 AND 20',
        ],
        'etapes_projet_guide' => [
            'chk_etapes_ordre' => 'ordre >= 1',
        ],
    ];

    public function up(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->checks as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->checks as $table => $constraints) {
            foreach (array_keys($constraints) as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            }
        }
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
    }
};
