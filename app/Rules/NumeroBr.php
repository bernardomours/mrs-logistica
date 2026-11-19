<?php

namespace App\Rules;

use App\Support\Numero;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Número digitado em pt-BR ("4.491,69", "6,5") dentro de um intervalo. */
class NumeroBr implements ValidationRule
{
    public function __construct(
        private readonly float $minimo = 0.0,
        private readonly ?float $maximo = null,
        private readonly int $casas = 2,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = Numero::paraDecimal((string) $value, $this->casas);

        if ($numero === null) {
            $fail('O campo :attribute deve ser um número válido.');

            return;
        }

        if ((float) $numero < $this->minimo) {
            $fail('O campo :attribute deve ser pelo menos '.Numero::formatar($this->minimo, $this->casas).'.');

            return;
        }

        if ($this->maximo !== null && (float) $numero > $this->maximo) {
            $fail('O campo :attribute não pode ser maior que '.Numero::formatar($this->maximo, $this->casas).'.');
        }
    }
}
