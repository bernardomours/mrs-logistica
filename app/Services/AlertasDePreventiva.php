<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\Manutencao;
use App\Models\Veiculo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Alertas de manutenção preventiva (RN-13/RN-20): vale a manutenção MAIS RECENTE do veículo que definiu
 * `proxima_km` e/ou `proxima_data` (uma nova manutenção com próxima substitui a anterior).
 *  - "vencida": hodômetro atual ≥ proxima_km ou hoje > proxima_data;
 *  - "proxima": faltam até `frota.preventiva_aviso_km` km ou `frota.preventiva_aviso_dias` dias.
 * O "hodômetro atual" é a maior leitura conhecida: abastecimentos, manutenções e o odômetro inicial do veículo.
 */
class AlertasDePreventiva
{
    /**
     * @param  ?array<int, int>  $veiculoIds  nulo = todos os veículos acessíveis
     * @return Collection<int, array{veiculo: Veiculo, manutencao: Manutencao, situacao: string, motivos: array<int, string>}>
     */
    public function handle(?array $veiculoIds = null): Collection
    {
        $planos = Manutencao::query()
            ->when($veiculoIds !== null, fn ($q) => $q->whereIn('veiculo_id', $veiculoIds))
            ->where(fn ($q) => $q->whereNotNull('proxima_km')->orWhereNotNull('proxima_data'))
            ->with('veiculo')
            ->orderByDesc('data_manutencao')->orderByDesc('id')
            ->get()
            ->unique('veiculo_id')
            ->filter(fn (Manutencao $m) => $m->veiculo !== null && $m->veiculo->ativo);

        if ($planos->isEmpty()) {
            return collect();
        }

        $ids = $planos->pluck('veiculo_id')->all();
        $porAbastecimento = Abastecimento::query()->whereIn('veiculo_id', $ids)->groupBy('veiculo_id')->selectRaw('veiculo_id, max(odometro_km) as maior')->pluck('maior', 'veiculo_id');
        $porManutencao = Manutencao::query()->whereIn('veiculo_id', $ids)->groupBy('veiculo_id')->selectRaw('veiculo_id, max(odometro_km) as maior')->pluck('maior', 'veiculo_id');
        $hoje = CarbonImmutable::today();

        return $planos->map(function (Manutencao $plano) use ($porAbastecimento, $porManutencao, $hoje) {
            $atual = collect([$porAbastecimento[$plano->veiculo_id] ?? null, $porManutencao[$plano->veiculo_id] ?? null, $plano->veiculo->odometro_inicial])
                ->filter(fn ($v) => $v !== null)->map(fn ($v) => (string) $v)
                ->sort(fn ($a, $b) => bccomp($b, $a, 1))->first();

            $situacao = null;
            $motivos = [];

            if ($plano->proxima_km !== null && $atual !== null) {
                $faltam = bcsub((string) $plano->proxima_km, $atual, 1);

                if (bccomp($faltam, '0', 1) <= 0) {
                    $situacao = 'vencida';
                    $motivos[] = 'Passou de '.number_format((float) $plano->proxima_km, 1, ',', '.').' km (hodômetro em '.number_format((float) $atual, 1, ',', '.').' km).';
                } elseif (bccomp($faltam, (string) config('frota.preventiva_aviso_km'), 1) <= 0) {
                    $situacao ??= 'proxima';
                    $motivos[] = 'Faltam '.number_format((float) $faltam, 1, ',', '.').' km para '.number_format((float) $plano->proxima_km, 1, ',', '.').' km.';
                }
            }

            if ($plano->proxima_data !== null) {
                $dias = (int) $hoje->diffInDays($plano->proxima_data->toImmutable(), false);

                if ($dias < 0) {
                    $situacao = 'vencida';
                    $motivos[] = 'Venceu em '.$plano->proxima_data->format('d/m/Y').'.';
                } elseif ($dias <= (int) config('frota.preventiva_aviso_dias')) {
                    $situacao ??= 'proxima';
                    $motivos[] = $dias === 0 ? 'Vence hoje.' : "Vence em {$dias} dia(s), {$plano->proxima_data->format('d/m/Y')}.";
                }
            }

            return $situacao === null ? null : ['veiculo' => $plano->veiculo, 'manutencao' => $plano, 'situacao' => $situacao, 'motivos' => $motivos];
        })->filter()->sortBy(fn ($a) => $a['situacao'] === 'vencida' ? 0 : 1)->values();
    }
}
