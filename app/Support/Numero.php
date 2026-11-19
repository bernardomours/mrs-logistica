<?php

namespace App\Support;

/**
 * Converte números digitados em pt-BR ("4.491,69", "R$ 4491,69", "6,5%") para decimal em string
 * ("4491.69") e vice-versa. Nunca usa float para dinheiro (D-08).
 */
final class Numero
{
    /**
     * Regras: com vírgula, a vírgula é o separador decimal e os pontos são milhar;
     * sem vírgula, um único ponto é decimal ("6.5") e vários pontos são milhar ("1.234.567").
     */
    public static function paraDecimal(?string $valor, int $casas = 2): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpo = preg_replace('/[^\d,.\-]/', '', str_replace(['R$', '%'], '', $valor)) ?? '';

        if ($limpo === '' || $limpo === '-') {
            return null;
        }

        if (str_contains($limpo, ',')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        } elseif (substr_count($limpo, '.') > 1) {
            $limpo = str_replace('.', '', $limpo);
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $limpo)) {
            return null;
        }

        return number_format(round((float) $limpo, $casas, PHP_ROUND_HALF_UP), $casas, '.', '');
    }

    /** Formata "4491.69" como "4.491,69". Retorna "" para nulo. */
    public static function formatar(string|int|float|null $valor, int $casas = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $casas, ',', '.');
    }

    /** Valor para exibir em campo de formulário: "4491.69" -> "4491,69" (sem milhar). */
    public static function paraCampo(string|int|float|null $valor, int $casas = 2): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return number_format((float) $valor, $casas, ',', '');
    }
}
