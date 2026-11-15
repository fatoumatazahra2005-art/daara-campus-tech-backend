<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utilisateur;
use App\Models\CodeList;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Vérifier si l'administrateur existe déjà
        $existe = Utilisateur::where('email', 'admin@codebuddy.sn')
            ->exists();

        if ($existe) {
            return;
        }

        // Créer le compte administrateur
        Utilisateur::create([
            'nom' => 'Admin',
            'prenom' => 'CodeBuddy',
            'email' => 'admin@codebuddy.sn',
            'mot_de_passe_hash' => Hash::make('Admin@12345'),
            'role_id' => CodeList::idOf('RoleUser', 'ADMIN'),
            'statut_compte_id' => CodeList::idOf('StatutCompte', 'ACTIF'),
        ]);
    }
}
