<?php

namespace App\Services\Relatorios;

use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/** Gasto com manutenção por veículo (informativo: não entra no líquido da semana — RN-20). */
class CustoDeManutencao implements Relatorio
{
    public static function chave(): string
    {
        return 'custo-de-manutencao';
    }

    public function titulo(): string
    {
        return 'Custo de manutenção';
    }

    public function descricao(): string
    {
        return 'Peças, mão de obra e total gasto por veículo, com a quantidade de preventivas e corretivas.';
    }

    public function permissoes(): array
    {
        return ['manutencoes.ver_valores'];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'tipo' => 'texto'],
            ['chave' => 'manutencoes', 'rotulo' => 'Manutenções', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'preventivas', 'rotulo' => 'Preventivas', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'corretivas', 'rotulo' => 'Corretivas', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'pecas', 'rotulo' => 'Peças (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'mao_de_obra', 'rotulo' => 'Mão de obra (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'total', 'rotulo' => 'Total (R$)', 'tipo' => 'dinheiro', 'somar' => true],
        ];
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $grupos = Manutencao::query()
            ->whereBetween('data_manutencao', [$periodo->de(), $periodo->ate()])
            ->selectRaw('veiculo_id, count(*) as qtd, sum(valor_pecas) as pecas, sum(valor_mao_obra) as mao, sum(valor_total) as total')
            ->selectRaw("sum(case when tipo = 'preventiva' then 1 else 0 end) as preventivas")
            ->selectRaw("sum(case when tipo = 'corretiva' then 1 else 0 end) as corretivas")
            ->groupBy('veiculo_id')->get();

        $placas = Veiculo::query()->whereIn('id', $grupos->pluck('veiculo_id'))->get(['id', 'placa'])->keyBy('id');

        return $grupos->map(fn ($g) => [
            'veiculo' => $placas[$g->veiculo_id]->placa_formatada ?? '—',
            'manutencoes' => (int) $g->qtd,
            'preventivas' => (int) $g->preventivas,
            'corretivas' => (int) $g->corretivas,
            'pecas' => Dinheiro::total([(string) $g->pecas]),
            'mao_de_obra' => Dinheiro::total([(string) $g->mao]),
            'total' => Dinheiro::total([(string) $g->total]),
        ])->sortByDesc(fn ($l) => (float) $l['total'])->values();
    }
}
