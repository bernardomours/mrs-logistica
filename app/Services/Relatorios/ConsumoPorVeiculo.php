<?php

namespace App\Services\Relatorios;

use App\Models\Abastecimento;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\PeriodoRelatorio;
use Illuminate\Support\Collection;

/**
 * Combustível por veículo no período. A média é a ponderada (Σ km ÷ Σ litros) só dos abastecimentos de tanque
 * cheio com média calculada (RN-08/RN-09); litros e valor somam todos.
 */
class ConsumoPorVeiculo implements Relatorio
{
    public static function chave(): string
    {
        return 'consumo-por-veiculo';
    }

    public function titulo(): string
    {
        return 'Consumo por veículo';
    }

    public function descricao(): string
    {
        return 'Litros, gasto, preço médio, km rodados, média km/l e custo por km de cada caminhão.';
    }

    public function permissoes(): array
    {
        return ['abastecimentos.ver'];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'veiculo', 'rotulo' => 'Veículo', 'tipo' => 'texto'],
            ['chave' => 'abastecimentos', 'rotulo' => 'Abastecimentos', 'tipo' => 'inteiro', 'somar' => true],
            ['chave' => 'litros', 'rotulo' => 'Litros', 'tipo' => 'decimal3', 'somar' => true],
            ['chave' => 'valor', 'rotulo' => 'Valor (R$)', 'tipo' => 'dinheiro', 'somar' => true],
            ['chave' => 'preco_medio', 'rotulo' => 'Preço médio/L (R$)', 'tipo' => 'decimal3'],
            ['chave' => 'km', 'rotulo' => 'Km rodados', 'tipo' => 'decimal1', 'somar' => true],
            ['chave' => 'media', 'rotulo' => 'Média km/l', 'tipo' => 'decimal2'],
            ['chave' => 'custo_km', 'rotulo' => 'Custo por km (R$)', 'tipo' => 'decimal2'],
        ];
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $grupos = Abastecimento::query()
            ->whereBetween('data_abastecimento', [$periodo->de(), $periodo->ate()])
            ->selectRaw('veiculo_id, count(*) as qtd, sum(litros) as litros, sum(valor_total) as valor')
            ->selectRaw('sum(case when km_rodados is not null then km_rodados else 0 end) as km')
            ->selectRaw('sum(case when tanque_cheio = 1 and media_km_l is not null then km_rodados else 0 end) as km_media')
            ->selectRaw('sum(case when tanque_cheio = 1 and media_km_l is not null then litros else 0 end) as litros_media')
            ->groupBy('veiculo_id')->get();

        $placas = Veiculo::query()->whereIn('id', $grupos->pluck('veiculo_id'))->get(['id', 'placa'])->keyBy('id');

        return $grupos->map(function ($g) use ($placas) {
            $litros = Dinheiro::total([(string) $g->litros], 3);
            $valor = Dinheiro::total([(string) $g->valor]);
            $km = Dinheiro::total([(string) $g->km], 1);

            return [
                'veiculo' => $placas[$g->veiculo_id]->placa_formatada ?? '—',
                'abastecimentos' => (int) $g->qtd,
                'litros' => $litros,
                'valor' => $valor,
                'preco_medio' => Dinheiro::dividir($valor, $litros, 3),
                'km' => $km,
                'media' => bccomp((string) $g->litros_media, '0', 3) > 0 ? Dinheiro::dividir((string) $g->km_media, (string) $g->litros_media, 2) : null,
                'custo_km' => Dinheiro::dividir($valor, $km, 2),
            ];
        })->sortBy('veiculo')->values();
    }
}
