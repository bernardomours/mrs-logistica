<?php

namespace App\Services\Relatorios;

use App\Support\Dinheiro;
use App\Support\Numero;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Formatação e totais das colunas de um relatório (tela e exportação usam o mesmo código). */
class Tabela
{
    /** Casas decimais de cada tipo numérico. */
    private const CASAS = ['inteiro' => 0, 'dinheiro' => 2, 'decimal1' => 1, 'decimal2' => 2, 'decimal3' => 3];

    public static function casas(string $tipo): int
    {
        return self::CASAS[$tipo] ?? 0;
    }

    public static function ehNumero(string $tipo): bool
    {
        return isset(self::CASAS[$tipo]);
    }

    public static function formatar(string $tipo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if ($tipo === 'data') {
            return $valor instanceof CarbonInterface ? $valor->format('d/m/Y') : (string) $valor;
        }

        return self::ehNumero($tipo) ? Numero::formatar($valor, self::casas($tipo)) : (string) $valor;
    }

    /**
     * Linha TOTAL: soma das colunas marcadas com `somar`; as demais ficam vazias.
     *
     * @param  array<int, array{chave: string, rotulo: string, tipo: string, somar?: bool}>  $colunas
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return array<string, ?string>
     */
    public static function totais(array $colunas, Collection $linhas): array
    {
        $totais = [];

        foreach ($colunas as $coluna) {
            $totais[$coluna['chave']] = ($coluna['somar'] ?? false)
                ? Dinheiro::total($linhas->pluck($coluna['chave']), self::casas($coluna['tipo']))
                : null;
        }

        return $totais;
    }
}
