<?php

namespace App\Services\Relatorios;

use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/**
 * Contrato dos relatórios (Fase 4). Cada relatório devolve linhas com as chaves das `colunas()`; a tela e a
 * exportação formatam pelo `tipo` da coluna. Quem não tem as `permissoes()` nem enxerga o relatório (RN-15).
 */
interface Relatorio
{
    /** Identificador na URL, ex.: "consumo-por-veiculo". */
    public static function chave(): string;

    public function titulo(): string;

    public function descricao(): string;

    /**
     * Permissões exigidas além de `relatorios.ver` (valores financeiros, RN-15).
     *
     * @return array<int, string>
     */
    public function permissoes(): array;

    /**
     * `tipo`: texto, inteiro, dinheiro, decimal1, decimal2, decimal3, data. `somar`: entra na linha TOTAL.
     *
     * @return array<int, array{chave: string, rotulo: string, tipo: string, somar?: bool}>
     */
    public function colunas(): array;

    /** @return Collection<int, array<string, mixed>> */
    public function linhas(PeriodoRelatorio $periodo): Collection;
}
