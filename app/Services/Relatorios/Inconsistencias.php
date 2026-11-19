<?php

namespace App\Services\Relatorios;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Viagem;
use App\Services\AlertasDePreventiva;
use App\Support\Numero;
use App\Support\PeriodoRelatorio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Alertas de RN-13 reunidos numa lista só. Nada aqui bloqueia lançamento: é para o escritório conferir.
 * Cobertura: média fora da faixa, litros acima do tanque, preço do litro fora do padrão, frete diferente da
 * tarifa, CNH vencida e manutenção preventiva vencida/próxima.
 * Não há "hodômetro sem variação" (a cadeia já recusa leitura repetida) nem "viagem sem abastecimento na semana"
 * (decidido em 18/11/2026: viagem não exige abastecimento, então esse alerta só gerava ruído).
 */
class Inconsistencias implements Relatorio
{
    public static function chave(): string
    {
        return 'inconsistencias';
    }

    public function titulo(): string
    {
        return 'Inconsistências';
    }

    public function descricao(): string
    {
        return 'Lançamentos e cadastros que merecem conferência: consumo, preço do combustível, frete, CNH e preventivas.';
    }

    public function permissoes(): array
    {
        return [];
    }

    public function colunas(): array
    {
        return [
            ['chave' => 'tipo', 'rotulo' => 'Alerta', 'tipo' => 'texto'],
            ['chave' => 'data', 'rotulo' => 'Data', 'tipo' => 'data'],
            ['chave' => 'referencia', 'rotulo' => 'Veículo / motorista', 'tipo' => 'texto'],
            ['chave' => 'detalhe', 'rotulo' => 'Detalhe', 'tipo' => 'texto'],
        ];
    }

    public function linhas(PeriodoRelatorio $periodo): Collection
    {
        $linhas = collect()
            ->merge($this->consumo($periodo))
            ->merge($this->precoDoLitro($periodo));

        if (Auth::user()?->can('viagens.ver_valores')) {
            $linhas = $linhas->merge($this->freteForaDaTarifa($periodo));
        }

        return $linhas
            ->merge($this->cnhVencida())
            ->merge($this->preventivas())
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function consumo(PeriodoRelatorio $periodo): Collection
    {
        return Abastecimento::query()->with(['veiculo:id,placa,capacidade_tanque_l', 'motorista:id,nome'])
            ->whereBetween('data_abastecimento', [$periodo->de(), $periodo->ate()])
            ->orderBy('data_abastecimento')->get()
            ->flatMap(fn (Abastecimento $a) => collect($a->alertas())->map(fn (string $alerta) => [
                'tipo' => str_contains($alerta, 'Média') ? 'Média fora da faixa' : 'Litros acima do tanque',
                'data' => $a->data_abastecimento,
                'referencia' => $a->veiculo->placa_formatada.' · '.$a->motorista->nome,
                'detalhe' => rtrim($alerta, '.').' ('.Numero::formatar($a->litros, 3).' L, hodômetro '.Numero::formatar($a->odometro_km, 1).').',
            ]));
    }

    /** Preço do litro mais de `preco_litro_desvio_pct`% distante da média dos 5 abastecimentos anteriores do veículo. */
    private function precoDoLitro(PeriodoRelatorio $periodo): Collection
    {
        $limite = (float) config('frota.preco_litro_desvio_pct');
        $doPeriodo = Abastecimento::query()->whereBetween('data_abastecimento', [$periodo->de(), $periodo->ate()])->pluck('veiculo_id')->unique();

        if ($doPeriodo->isEmpty()) {
            return collect();
        }

        $historico = Abastecimento::query()->with(['veiculo:id,placa', 'motorista:id,nome'])
            ->whereIn('veiculo_id', $doPeriodo)->where('data_abastecimento', '<=', $periodo->ate())
            ->orderBy('veiculo_id')->orderBy('odometro_km')->get();

        $linhas = collect();

        foreach ($historico->groupBy('veiculo_id') as $doVeiculo) {
            $anteriores = [];

            foreach ($doVeiculo as $a) {
                $preco = (float) $a->preco_litro;

                if (count($anteriores) >= 3 && $a->data_abastecimento->betweenIncluded($periodo->inicio, $periodo->fim)) {
                    $media = array_sum($anteriores) / count($anteriores);
                    $desvio = $media > 0 ? abs($preco - $media) / $media * 100 : 0;

                    if ($desvio > $limite) {
                        $linhas->push([
                            'tipo' => 'Preço do litro fora do padrão',
                            'data' => $a->data_abastecimento,
                            'referencia' => $a->veiculo->placa_formatada.' · '.$a->motorista->nome,
                            'detalhe' => 'R$ '.Numero::formatar($preco, 3).'/L contra média de R$ '.Numero::formatar($media, 3).'/L dos '.count($anteriores).' anteriores ('.($preco > $media ? '+' : '−').Numero::formatar($desvio, 1).'%).',
                        ]);
                    }
                }

                $anteriores[] = $preco;
                $anteriores = array_slice($anteriores, -5);
            }
        }

        return $linhas->sortBy(fn ($l) => $l['data']->timestamp)->values();
    }

    private function freteForaDaTarifa(PeriodoRelatorio $periodo): Collection
    {
        return Viagem::query()->concluidas()->whereNotNull('tarifa_rota_id')
            ->with(['tarifa', 'origem:id,nome', 'destino:id,nome', 'motorista:id,nome'])
            ->whereBetween('data_viagem', [$periodo->de(), $periodo->ate()])
            ->orderBy('data_viagem')->get()
            ->filter(fn (Viagem $v) => $v->divergeDaTarifa())
            ->map(fn (Viagem $v) => [
                'tipo' => 'Frete diferente da tarifa',
                'data' => $v->data_viagem,
                'referencia' => $v->motorista->nome.' · '.$v->origem->nome.' → '.$v->destino->nome,
                'detalhe' => 'Frete R$ '.Numero::formatar($v->valor_frete).' contra tarifa de R$ '.Numero::formatar($v->tarifa->valor_frete).'.',
            ])->values();
    }

    private function cnhVencida(): Collection
    {
        return Motorista::query()->ativos()->whereNotNull('cnh_validade')->whereDate('cnh_validade', '<', CarbonImmutable::today())
            ->orderBy('cnh_validade')->get(['id', 'nome', 'cnh_validade'])
            ->map(fn (Motorista $m) => [
                'tipo' => 'CNH vencida',
                'data' => $m->cnh_validade,
                'referencia' => $m->nome,
                'detalhe' => 'CNH venceu em '.$m->cnh_validade->format('d/m/Y').'.',
            ]);
    }

    private function preventivas(): Collection
    {
        return app(AlertasDePreventiva::class)->handle()->map(fn (array $a) => [
            'tipo' => $a['situacao'] === 'vencida' ? 'Preventiva vencida' : 'Preventiva se aproximando',
            'data' => $a['manutencao']->data_manutencao,
            'referencia' => $a['veiculo']->placa_formatada,
            'detalhe' => implode(' ', $a['motivos']),
        ]);
    }
}
