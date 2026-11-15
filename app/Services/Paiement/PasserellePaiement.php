<?php

namespace App\Services\Paiement;

use App\Models\Paiement;

/**
 * Contrat avec l'opérateur (Wave, Orange Money, Free Money, carte).
 * Le mobile money ne permet pas de prélèvement sans action du client : demander() envoie la demande
 * de paiement sur le téléphone (numeroPayeur) ; la confirmation arrive plus tard par callback.
 */
interface PasserellePaiement
{
    /** Envoie la demande à l'opérateur et retourne la référence de transaction. */
    public function demander(Paiement $paiement): string;
}
