<?php

namespace App\Support;

/**
 * Aritmética decimal exata (bcmath) com arredondamento *half-up* (CLAUDE.md D-08).
 * Valores são sempre `string` ("4491.69"); nunca `float`.
 */
final class Dinheiro
{
    private const ESCALA_INTERNA = 12;

    /** Arredonda para `$casas` casas, metade para cima (afastando de zero). */
    public static function arredondar(string $valor, int $casas = 2): string
    {
        $negativo = str_starts_with($valor, '-');
        $absoluto = ltrim($valor, '-+');
        $absoluto = $absoluto === '' ? '0' : $absoluto;

        $arredondado = bcadd($absoluto, '0.'.str_repeat('0', $casas).'5', $casas);

        return ($negativo && bccomp($arredondado, '0', $casas) !== 0 ? '-' : '').$arredondado;
    }

    public static function somar(string $a, string $b, int $casas = 2): string
    {
        return bcadd($a, $b, $casas);
    }

    public static function subtrair(string $a, string $b, int $casas = 2): string
    {
        return bcsub($a, $b, $casas);
    }

    public static function multiplicar(string $a, string $b, int $casas = 2): string
    {
        return self::arredondar(bcmul($a, $b, self::ESCALA_INTERNA), $casas);
    }

    /** Divisão; devolve `null` se o divisor for zero. */
    public static function dividir(string $a, string $b, int $casas = 2): ?string
    {
        if (bccomp($b, '0', self::ESCALA_INTERNA) === 0) {
            return null;
        }

        return self::arredondar(bcdiv($a, $b, self::ESCALA_INTERNA), $casas);
    }

    /** `valor × percentual ÷ 100` arredondado (comissão — RN-02). */
    public static function percentual(string $valor, string $percentual, int $casas = 2): string
    {
        return self::arredondar(bcdiv(bcmul($valor, $percentual, self::ESCALA_INTERNA), '100', self::ESCALA_INTERNA), $casas);
    }

    /** Soma uma lista de valores. */
    public static function total(iterable $valores, int $casas = 2): string
    {
        $total = '0';

        foreach ($valores as $valor) {
            $total = bcadd($total, (string) $valor, self::ESCALA_INTERNA);
        }

        return self::arredondar($total, $casas);
    }

    /** -1, 0 ou 1. */
    public static function comparar(string $a, string $b, int $casas = 2): int
    {
        return bccomp($a, $b, $casas);
    }

    /** Valor absoluto. */
    public static function abs(string $valor): string
    {
        return ltrim($valor, '-');
    }
}
