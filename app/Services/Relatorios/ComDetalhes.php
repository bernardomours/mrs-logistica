<?php

namespace App\Services\Relatorios;

use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/**
 * Relatório que, além da tabela-resumo, mostra tabelas de detalhe lado a lado (ex.: receitas | despesas).
 * Cada lado traz as linhas que compõem o total; `truncado` avisa que a lista foi cortada (os totais continuam completos).
 */
interface ComDetalhes
{
    /**
     * @param  Collection<int, array<string, mixed>>  $linhas  linhas do resumo (já no escopo de filial do usuário)
     * @return array<int, array{titulo: string, tom: string, colunas: array<int, array{chave: string, rotulo: string, tipo: string}>, linhas: Collection<int, array<string, mixed>>, quantidade: int, total: string, truncado: bool, vazio: string}>
     */
    public function detalhes(PeriodoRelatorio $periodo, Collection $linhas): array;
}
