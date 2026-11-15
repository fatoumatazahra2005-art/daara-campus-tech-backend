<?php

return [
    /*
     * Simulateur d'opérateur (page /simulateur-operateur) : imite le téléphone du payeur
     * qui reçoit la demande de paiement mobile money. Ne jamais activer en production.
     */
    'simulation' => (bool) env('PAIEMENT_SIMULATION', false),
];
