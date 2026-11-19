<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Manutencao;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\PeriodoRelatorio;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * KPIs do dashboard no período (escopo de filial do usuário logado). Sempre calculados por consulta (D-15);
 * valores são `string` decimal. Quem exibe decide o que cada usuário pode ver (RN-15).
 */
class Indicadores
{
    /**
     * @return array{viagens: int, frete: string, comissao: string, abastecimento: string, liquido: string, manutencao: string, litros: string, km: string, media: ?string}
     */
    public function paraPeriodo(PeriodoRelatorio $periodo): array
    {
        $v = Viagem::query()->concluidas()->whereBetween('data_viagem', [$periodo->de(), $periodo->ate()])
            ->selectRaw('count(*) as qtd, coalesce(sum(valor_frete), 0) as frete, coalesce(sum(comissao_valor), 0) as comissao')
            ->toBase()->first();

        $a = Abastecimento::query()->whereBetween('data_abastecimento', [$periodo->de(), $periodo->ate()])
            ->selectRaw('coalesce(sum(valor_total), 0) as valor, coalesce(sum(litros), 0) as litros')
            ->selectRaw('coalesce(sum(case when km_rodados is not null then km_rodados else 0 end), 0) as km')
            ->selectRaw('coalesce(sum(case when tanque_cheio = 1 and media_km_l is not null then km_rodados else 0 end), 0) as km_media')
            ->selectRaw('coalesce(sum(case when tanque_cheio = 1 and media_km_l is not null then litros else 0 end), 0) as litros_media')
            ->toBase()->first();

        $manutencao = Manutencao::query()->whereBetween('data_manutencao', [$periodo->de(), $periodo->ate()])->sum('valor_total');

        $frete = Dinheiro::total([(string) $v->frete]);
        $comissao = Dinheiro::total([(string) $v->comissao]);
        $combustivel = Dinheiro::total([(string) $a->valor]);

        return [
            'viagens' => (int) $v->qtd,
            'frete' => $frete,
            'comissao' => $comissao,
            'abastecimento' => $combustivel,
            'liquido' => Dinheiro::subtrair(Dinheiro::subtrair($frete, $comissao), $combustivel),
            'manutencao' => Dinheiro::total([(string) $manutencao]),
            'litros' => Dinheiro::total([(string) $a->litros], 3),
            'km' => Dinheiro::total([(string) $a->km], 1),
            // Média ponderada do período (Σ km ÷ Σ litros dos abastecimentos de tanque cheio com média).
            'media' => bccomp((string) $a->litros_media, '0', 3) > 0 ? Dinheiro::dividir((string) $a->km_media, (string) $a->litros_media, 2) : null,
        ];
    }

    /**
     * Semanas de motorista que já passaram, têm movimento e ainda não foram fechadas (últimas `$semanas` semanas).
     *
     * @return Collection<int, array{motorista_id: int, semana: SemanaIso}>
     */
    public function semanasSemFechamento(int $semanas = 8): Collection
    {
        $atual = SemanaIso::atual();
        $inicio = $atual->inicio->subWeeks($semanas);
        $fim = $atual->inicio->subDay();

        $movimentos = Viagem::query()->whereBetween('data_viagem', [$inicio->toDateString(), $fim->toDateString()])->get(['motorista_id', 'data_viagem'])
            ->map(fn ($x) => [$x->motorista_id, SemanaIso::deData($x->data_viagem)])
            ->concat(Abastecimento::query()->whereBetween('data_abastecimento', [$inicio->toDateString(), $fim->toDateString()])->get(['motorista_id', 'data_abastecimento'])
                ->map(fn ($x) => [$x->motorista_id, SemanaIso::deData($x->data_abastecimento)]))
            ->unique(fn ($par) => $par[0].'|'.$par[1]->chave());

        $fechadas = FechamentoSemanal::query()->whereBetween('data_inicio', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['motorista_id', 'ano_iso', 'semana_iso'])
            ->map(fn ($f) => $f->motorista_id.'|'.sprintf('%d-W%02d', $f->ano_iso, $f->semana_iso))->flip();

        return $movimentos->reject(fn ($par) => $fechadas->has($par[0].'|'.$par[1]->chave()))
            ->map(fn ($par) => ['motorista_id' => $par[0], 'semana' => $par[1]])->values();
    }

    /** O período termina antes de hoje? (para rotular "parcial" no dashboard) */
    public function emAndamento(PeriodoRelatorio $periodo): bool
    {
        return $periodo->fim->greaterThanOrEqualTo(CarbonImmutable::today());
    }
}
