<?php

namespace App\Services\Relatorios;

use Illuminate\Support\Collection;

/**
 * Relatório que mostra cartões de resumo acima da tabela. Os cartões saem das mesmas linhas da tabela
 * (já limitadas ao escopo de filial e às permissões), então nunca divergem dela.
 *
 * `tom`: neutro, receita ou despesa. `detalhe` é uma frase curta opcional sob o valor.
 */
interface ComCartoes
{
    /**
     * @param  Collection<int, array<string, mixed>>  $linhas
     * @return array{principais: array<int, array{rotulo: string, valor: string, tom: string, detalhe?: string}>, detalhes: array<int, array{rotulo: string, valor: string, tom: string, detalhe?: string}>}
     */
    public function cartoes(Collection $linhas): array;
}
