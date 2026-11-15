<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Valeurs initiales du référentiel CodeList (voir « Valeurs initiales » du dictionnaire).
 * À lancer avant toute création d'utilisateur : les colonnes *_id des autres tables
 * pointent vers ces lignes (ACTIF, DEBUTANT, BROUILLON, etc.).
 */
class CodeListSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // type => [estSysteme (bool|array des values système), [values...]]
        $listes = [
            'RoleUser'          => [true, ['APPRENANT', 'ENCADREUR', 'ADMIN']],
            'StatutCompte'      => [true, ['ACTIF', 'BLOQUE', 'DESACTIVE']],
            'NiveauApprenant'   => [['DEBUTANT'], ['DEBUTANT', 'INTERMEDIAIRE', 'AVANCE']],
            'StatutCours'       => [true, ['BROUILLON', 'EN_ATTENTE_VALIDATION', 'PUBLIE', 'REFUSE', 'ARCHIVE']],
            'StatutParcours'    => [true, ['BROUILLON', 'PUBLIE', 'ARCHIVE']],
            'StatutInscription' => [true, ['EN_COURS', 'TERMINEE', 'ABANDONNEE']],
            'Periodicite'       => [true, ['MENSUEL', 'SEMESTRIEL', 'ANNUEL']],
            'StatutAbonnement'  => [true, ['EN_ATTENTE_PAIEMENT', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE', 'EXPIRE']],
            'ModeQuiz'          => [true, ['ENTRAINEMENT', 'EVALUATION']],
            'TypeQuestion'      => [true, ['VRAI_FAUX', 'CHOIX_UNIQUE', 'CHOIX_MULTIPLE']],
            'StatutTentative'   => [true, ['EN_COURS', 'REUSSI', 'ECHOUE']],
            'MethodePaiement'   => [false, ['WAVE', 'ORANGE_MONEY', 'FREE_MONEY', 'CARTE']],
            'StatutPaiement'    => [true, ['EN_ATTENTE', 'VALIDE', 'ECHOUE']],
            'StatutRendu'       => [true, ['A_FAIRE', 'RENDU', 'A_REPRENDRE', 'CORRIGE', 'EN_RETARD']],
            'Langue' => [true, ['FR', 'EN']],
        ];

        foreach ($listes as $type => [$systeme, $values]) {
            foreach ($values as $index => $value) {
                $estSysteme = is_array($systeme) ? in_array($value, $systeme, true) : $systeme;

                DB::table('code_lists')->updateOrInsert(
                    ['type' => $type, 'value' => $value],
                    [
                        'ordre'         => $index + 1,
                        'active'        => true,
                        'est_systeme'   => $estSysteme,
                        'date_creation' => $now->toDateString(),
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]
                );
            }
        }
    }
}
