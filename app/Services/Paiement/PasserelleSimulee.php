<?php

namespace App\Services\Paiement;

use App\Models\Paiement;
use Illuminate\Support\Facades\Log;

/**
 * Implémentation de test : ne contacte aucun opérateur. Utilise `php artisan paiement:simuler {reference}`
 * pour simuler la confirmation. À remplacer par une vraie passerelle en production.
 */
class PasserelleSimulee implements PasserellePaiement
{
    public function demander(Paiement $paiement): string
    {
        $reference = 'SIM-'.strtoupper(bin2hex(random_bytes(6)));

        Log::info('Paiement simulé demandé', ['paiement' => $paiement->id, 'montant' => $paiement->montant, 'reference' => $reference]);

        return $reference;
    }
}
