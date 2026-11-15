<?php

namespace App\Services;

use App\Models\Utilisateur;
use App\Models\Cours;
use App\Models\Parcours;
use App\Models\Abonnement;
use App\Models\Paiement;

class DashboardService
{
    /**
     * Récupérer les statistiques générales de la plateforme.
     */
    public function statistiques(): array
    {
        return [

            // Statistiques des utilisateurs
            'utilisateurs' => [
                'total' => Utilisateur::count(),

                'apprenants' => Utilisateur::whereHas('role', function ($query) {
                    $query->where('type', 'RoleUser')
                        ->where('value', 'APPRENANT');
                })->count(),

                'encadreurs' => Utilisateur::whereHas('role', function ($query) {
                    $query->where('type', 'RoleUser')
                        ->where('value', 'ENCADREUR');
                })->count(),

                'administrateurs' => Utilisateur::whereHas('role', function ($query) {
                    $query->where('type', 'RoleUser')
                        ->where('value', 'ADMIN');
                })->count(),

                'actifs' => Utilisateur::whereHas('statutCompte', function ($query) {
                    $query->where('value', 'ACTIF');
                })->count(),

                'bloques' => Utilisateur::whereHas('statutCompte', function ($query) {
                    $query->where('value', 'BLOQUE');
                })->count(),

                'desactives' => Utilisateur::whereHas('statutCompte', function ($query) {
                    $query->where('value', 'DESACTIVE');
                })->count(),
            ],

            // Statistiques des cours
            'cours' => [
                'total' => Cours::count(),

                'brouillons' => Cours::whereHas('statut', function ($query) {
                    $query->where('value', 'BROUILLON');
                })->count(),

                'en_attente' => Cours::whereHas('statut', function ($query) {
                    $query->where('value', 'EN_ATTENTE_VALIDATION');
                })->count(),

                'publies' => Cours::whereHas('statut', function ($query) {
                    $query->where('value', 'PUBLIE');
                })->count(),

                'refuses' => Cours::whereHas('statut', function ($query) {
                    $query->where('value', 'REFUSE');
                })->count(),

                'archives' => Cours::whereHas('statut', function ($query) {
                    $query->where('value', 'ARCHIVE');
                })->count(),
            ],

            // Statistiques des parcours
            'parcours' => [
                'total' => Parcours::count(),

                'publies' => Parcours::whereHas('statut', function ($query) {
                    $query->where('value', 'PUBLIE');
                })->count(),

                'brouillons' => Parcours::whereHas('statut', function ($query) {
                    $query->where('value', 'BROUILLON');
                })->count(),

                'archives' => Parcours::whereHas('statut', function ($query) {
                    $query->where('value', 'ARCHIVE');
                })->count(),
            ],

            // Statistiques des abonnements
            'abonnements' => [
                'total' => Abonnement::count(),

                'actifs' => Abonnement::whereHas('statut', function ($query) {
                    $query->where('value', 'ACTIF');
                })->count(),

                'en_attente' => Abonnement::whereHas('statut', function ($query) {
                    $query->where('value', 'EN_ATTENTE_PAIEMENT');
                })->count(),

                'expires' => Abonnement::whereHas('statut', function ($query) {
                    $query->where('value', 'EXPIRE');
                })->count(),

                'suspendus' => Abonnement::whereHas('statut', function ($query) {
                    $query->where('value', 'SUSPENDU');
                })->count(),
            ],

            // Statistiques des paiements
            'paiements' => [
                'total' => Paiement::count(),

                'valides' => Paiement::whereHas('statut', function ($query) {
                    $query->where('value', 'VALIDE');
                })->count(),

                'en_attente' => Paiement::whereHas('statut', function ($query) {
                    $query->where('value', 'EN_ATTENTE');
                })->count(),

                'echoues' => Paiement::whereHas('statut', function ($query) {
                    $query->where('value', 'ECHOUE');
                })->count(),

                'montant_total_valide' => Paiement::whereHas('statut', function ($query) {
                    $query->where('value', 'VALIDE');
                })->sum('montant'),
            ],
        ];
    }
}
