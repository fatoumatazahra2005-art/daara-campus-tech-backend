<?php

namespace App\Models\Concerns;

/**
 * Pour les modèles qui ont une relation statut() vers CodeList.
 * Ex. : $modele->aStatut('PUBLIE'), $modele->aStatut('EN_COURS', 'TERMINEE').
 */
trait AStatut
{
    public function aStatut(string ...$valeurs): bool
    {
        $this->loadMissing('statut');

        return in_array($this->statut?->value, $valeurs, true);
    }
}
