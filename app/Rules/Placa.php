<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Placa brasileira: padrão antigo (ABC1234) ou Mercosul (ABC1D23), com ou sem hífen. */
class Placa implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valida((string) $value)) {
            $fail('O campo :attribute deve ser uma placa válida (ABC-1234 ou ABC1D23).');
        }
    }

    public static function valida(string $valor): bool
    {
        return (bool) preg_match('/^[A-Z]{3}\d[A-Z\d]\d{2}$/', self::normalizar($valor));
    }

    /** "abc-1d23" => "ABC1D23". */
    public static function normalizar(string $valor): string
    {
        return mb_strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $valor));
    }
}
