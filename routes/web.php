<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\SimulateurOperateurController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verifier/{code}', function (string $code) {
    $c = \App\Models\Certificat::where('code_verification', strtoupper(trim($code)))->first();

    return view('certificats.verification', ['c' => $c, 'code' => $code]);
});



// Simulateur d'opérateur mobile money (PAIEMENT_SIMULATION=true uniquement)
Route::prefix('simulateur-operateur')
    ->name('simulateur.')
    ->controller(SimulateurOperateurController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/numero', 'changerNumero')->name('numero');
        Route::get('/demandes/{reference}', 'demande')->name('demande');
        Route::post('/demandes/{reference}', 'repondre')->name('repondre');
    });
