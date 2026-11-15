<?php

namespace App\Support;

/**
 * Numéros de mobile sénégalais.
 * Format unique enregistré en base : +221 suivi de 9 chiffres, sans espace (ex. +221771234567).
 */
class Telephone
{
    /** Préfixes mobiles acceptés. */
    public const PREFIXES = ['70', '75', '76', '77', '78'];

    /** Préfixes acceptés par méthode de paiement mobile money (CARTE : pas de numéro). */
    public const PAR_OPERATEUR = [
        'WAVE' => ['70', '75', '76', '77', '78'],
        'ORANGE_MONEY' => ['77', '78'],
        'FREE_MONEY' => ['76'],
    ];

    /** Les 9 chiffres du numéro (ex. 771234567), ou null s'il n'est pas valide. */
    public static function local(?string $numero): ?string
    {
        $n = preg_replace('/[\s.\-()]/', '', (string) $numero);
        $n = preg_replace('/^(\+221|00221|221)/', '', $n);

        if (! preg_match('/^\d{9}$/', $n) || ! in_array(substr($n, 0, 2), self::PREFIXES, true)) {
            return null;
        }

        return $n;
    }

    /** Format enregistré : +221771234567, ou null s'il n'est pas valide. */
    public static function normaliser(?string $numero): ?string
    {
        $n = self::local($numero);

        return $n === null ? null : '+221'.$n;
    }

    /** Le numéro (9 chiffres ou +221…) correspond-il à l'opérateur de la méthode de paiement ? */
    public static function compatibleAvec(string $numero, string $methode): bool
    {
        $n = self::local($numero);
        $prefixes = self::PAR_OPERATEUR[$methode] ?? null;

        return $n !== null && ($prefixes === null || in_array(substr($n, 0, 2), $prefixes, true));
    }
}
