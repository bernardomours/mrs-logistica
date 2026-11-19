<?php

namespace App\Actions\Abastecimentos;

use App\Actions\Lancamentos\ResolverMotoristaEVeiculo;
use App\Models\Abastecimento;
use App\Models\User;
use App\Services\TravaDeSemana;
use App\Services\TripeAbastecimento;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;

class AtualizarAbastecimento
{
    public function __construct(
        private readonly ResolverMotoristaEVeiculo $vinculos,
        private readonly TripeAbastecimento $tripe,
        private readonly RecalcularCadeia $cadeia,
        private readonly CriarAbastecimento $criar,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * Se o veículo mudar, as cadeias dos dois veículos são recalculadas.
     *
     * @param  array<string, mixed>  $dados  mesmo formato de `CriarAbastecimento`
     */
    public function handle(Abastecimento $abastecimento, array $dados, User $usuario): Abastecimento
    {
        [$motorista, $veiculo] = $this->vinculos->handle(
            (int) $dados['motorista_id'], (int) $dados['veiculo_id'], $abastecimento->motorista_id, $abastecimento->veiculo_id, $usuario,
        );

        $valores = $this->tripe->completar($dados['litros'] ?? null, $dados['preco_litro'] ?? null, $dados['valor_total'] ?? null);
        $this->criar->validarHodometro($veiculo, (string) $dados['odometro_km'], $dados['km_anterior'] ?? null, $abastecimento->id);

        // RN-11: a semana de origem e a de destino precisam estar abertas.
        $this->trava->garantirAberta($abastecimento->motorista_id, $abastecimento->data_abastecimento, 'data_abastecimento');
        $this->trava->garantirAberta($motorista->id, $dados['data_abastecimento'], 'data_abastecimento');

        // Só mexe na cadeia (e portanto no abastecimento seguinte) se o veículo ou o hodômetro mudaram.
        $mudouPosicao = $abastecimento->veiculo_id !== $veiculo->id
            || Dinheiro::comparar((string) $dados['odometro_km'], (string) $abastecimento->odometro_km, 1) !== 0;

        if ($mudouPosicao) {
            $this->trava->garantirSucessorLivre($abastecimento->veiculo_id, (string) $abastecimento->odometro_km, $abastecimento->id);
            $this->trava->garantirSucessorLivre($veiculo->id, (string) $dados['odometro_km'], $abastecimento->id);
        }

        return DB::transaction(function () use ($abastecimento, $dados, $usuario, $motorista, $veiculo, $valores) {
            $veiculoAnterior = $abastecimento->veiculo_id;

            $abastecimento->fill($valores + [
                'filial_id' => $motorista->filial_id,
                'veiculo_id' => $veiculo->id,
                'motorista_id' => $motorista->id,
                'posto_id' => $dados['posto_id'] ?? null,
                'data_abastecimento' => $dados['data_abastecimento'],
                'odometro_km' => $dados['odometro_km'],
                'tanque_cheio' => $dados['tanque_cheio'] ?? true,
                'combustivel' => $dados['combustivel'] ?? 'diesel',
                'nota_fiscal' => $dados['nota_fiscal'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);

            // Só vale como "digitado à mão" se este for o 1º da cadeia; do contrário a cadeia sobrescreve.
            $abastecimento->km_anterior = $dados['km_anterior'] ?? null;

            $abastecimento->updated_by = $usuario->id;
            $abastecimento->save();

            $this->cadeia->handle($veiculo->id);

            if ($veiculoAnterior !== $veiculo->id) {
                $this->cadeia->handle($veiculoAnterior);
            }

            return $abastecimento->refresh();
        });
    }
}
