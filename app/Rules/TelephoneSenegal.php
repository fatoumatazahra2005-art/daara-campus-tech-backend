<?php

namespace App\Rules;

use App\Support\Telephone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelephoneSenegal implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && $value !== '' && Telephone::normaliser((string) $value) === null) {
            $fail('Numéro invalide : 9 chiffres commençant par 70, 75, 76, 77 ou 78 (ex. 77 123 45 67).');
        }
    }
}
