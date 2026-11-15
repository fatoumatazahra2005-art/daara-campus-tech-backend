<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Paiement\PasserellePaiement;
use App\Services\Paiement\PasserelleSimulee;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PasserellePaiement::class,
            PasserelleSimulee::class
        );
    }

    public function boot(): void
    {
        //
    }
}
