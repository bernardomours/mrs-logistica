<?php

namespace App\Services;

use App\Support\Dinheiro;
use Illuminate\Validation\ValidationException;

/**
 * RN-06 — litros, preço por litro e valor total: informando 2 dos 3, o sistema calcula o 3º.
 * Se os 3 forem informados, `litros × preço` precisa bater com o valor (tolerância de centavos).
 */
class TripeAbastecimento
{
    /**
     * @param  ?string  $litros  decimal "367.647" ou null
     * @param  ?string  $preco  decimal "6.800" ou null
     * @param  ?string  $valor  decimal "2499.99" ou null
     * @return array{litros: string, preco_litro: string, valor_total: string}
     */
    public function completar(?string $litros, ?string $preco, ?string $valor): array
    {
        $informados = array_filter([$litros, $preco, $valor], fn ($v) => $v !== null && $v !== '');

        if (count($informados) < 2) {
            throw ValidationException::withMessages([
                'valor_total' => 'Informe pelo menos dois entre litros, preço por litro e valor total.',
            ]);
        }

        foreach (['litros' => $litros, 'preco_litro' => $preco, 'valor_total' => $valor] as $campo => $v) {
            if ($v !== null && $v !== '' && Dinheiro::comparar($v, '0', 3) <= 0) {
                throw ValidationException::withMessages([$campo => 'O valor precisa ser maior que zero.']);
            }
        }

        if ($valor === null || $valor === '') {
            $valor = Dinheiro::multiplicar($litros, $preco, 2);
        } elseif ($litros === null || $litros === '') {
            $litros = Dinheiro::dividir($valor, $preco, 3);
        } elseif ($preco === null || $preco === '') {
            $preco = Dinheiro::dividir($valor, $litros, 3);
        } else {
            $esperado = Dinheiro::multiplicar($litros, $preco, 2);
            $diferenca = Dinheiro::abs(Dinheiro::subtrair($esperado, $valor));

            if (Dinheiro::comparar($diferenca, (string) config('frota.tolerancia_valor_abastecimento')) > 0) {
                throw ValidationException::withMessages([
                    'valor_total' => 'Litros × preço = R$ '.number_format((float) $esperado, 2, ',', '.').', diferente do valor informado.',
                ]);
            }
        }

        return [
            'litros' => Dinheiro::arredondar($litros, 3),
            'preco_litro' => Dinheiro::arredondar($preco, 3),
            'valor_total' => Dinheiro::arredondar($valor, 2),
        ];
    }
}
