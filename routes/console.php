<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\Abonnement;
use App\Services\Apprenant\PaiementService;
use Illuminate\Support\Facades\Schedule;

// Fin des essais gratuits : ACTIF si déjà payé, sinon EN_ATTENTE_PAIEMENT.
Schedule::call(function () {
    $service = app(PaiementService::class);

    Abonnement::whereHas('statut', fn ($q) => $q->where('value', 'ESSAI'))
        ->get()
        ->each(fn ($a) => $service->actualiserFinEssai($a));
})->dailyAt('00:10')->name('fin-essais');
