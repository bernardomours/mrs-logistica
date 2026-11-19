<?php

namespace App\Services\Relatorios;

use App\Models\Local;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/** Viagens concluídas agrupadas por rota (origem → destino), da que mais fatura para a que menos fatura. */
class FaturamentoPorRota implements Relatorio
{
    public static function chave(): string
    {
        return 'faturamento-por-rota';
    }

    public function titulo(): string
    {
        return 'Faturamento por rota';
    }

    public function descricao(): string
    {
        return 'Quantas viagens cada rota teve, o frete total e médio e a comissão paga.';
    }

    public function permissoes(): array
    {
        return ['viagens.ver_valores'];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'rota', 'rotulo' => 'Rota', 'tipo' => 'texto'],
            ['chave' => 'viagens', 'rotulo' => 'Viagens', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'frete', 'rotulo' => 'Frete total (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'frete_medio', 'rotulo' => 'Frete médio (R$)', 'tipo' => 'dinheiro'],
            ['chave' => 'comissao', 'rotulo' => 'Comissão (R$)', 'tipo' => 'dinheiro', 'somar' => true],
        ];
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $grupos = Viagem::query()->concluidas()
            ->whereBetween('data_viagem', [$periodo->de(), $periodo->ate()])
            ->selectRaw('origem_local_id, destino_local_id, count(*) as qtd, sum(valor_frete) as frete, sum(comissao_valor) as comissao')
            ->groupBy('origem_local_id', 'destino_local_id')->get();

        $locais = Local::query()->whereIn('id', $grupos->pluck('origem_local_id')->merge($grupos->pluck('destino_local_id')))->pluck('nome', 'id');

        return $grupos->map(fn ($g) => [
            'rota' => ($locais[$g->origem_local_id] ?? '—').' → '.($locais[$g->destino_local_id] ?? '—'),
            'viagens' => (int) $g->qtd,
            'frete' => Dinheiro::total([(string) $g->frete]),
            'frete_medio' => Dinheiro::dividir((string) $g->frete, (string) $g->qtd, 2),
            'comissao' => Dinheiro::total([(string) $g->comissao]),
        ])->sortByDesc(fn ($l) => (float) $l['frete'])->values();
    }
}
