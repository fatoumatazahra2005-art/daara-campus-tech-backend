<?php

namespace App\Providers;

use App\Services\Paiement\PasserellePaiement;
use App\Services\Paiement\PasserelleSimulee;
use Illuminate\Support\ServiceProvider;

class ApprenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Remplacez PasserelleSimulee par votre implémentation Wave / Orange Money en production.
        $this->app->bind(PasserellePaiement::class, PasserelleSimulee::class);
    }
}
