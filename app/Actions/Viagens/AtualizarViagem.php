<?php

namespace App\Actions\Viagens;

use App\Actions\Lancamentos\ResolverMotoristaEVeiculo;
use App\Models\User;
use App\Models\Viagem;
use App\Services\SugestorDeFrete;
use App\Services\TravaDeSemana;
use App\Support\Dinheiro;
use Illuminate\Validation\ValidationException;

class AtualizarViagem
{
    public function __construct(
        private readonly ResolverMotoristaEVeiculo $vinculos,
        private readonly SugestorDeFrete $sugestor,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * Mantém o snapshot da comissão (RN-02): troca o percentual só se o motorista mudou (passa a valer o do novo)
     * ou se quem tem `comissoes.editar` informar outro. O valor da comissão é sempre recalculado.
     *
     * @param  array<string, mixed>  $dados
     */
    public function handle(Viagem $viagem, array $dados, User $usuario): Viagem
    {
        if ($viagem->estaCancelada()) {
            throw ValidationException::withMessages(['status' => 'Viagem cancelada não pode ser alterada.']);
        }

        [$motorista] = $this->vinculos->handle(
            (int) $dados['motorista_id'], (int) $dados['veiculo_id'], $viagem->motorista_id, $viagem->veiculo_id, $usuario,
        );

        CriarViagem::validarRota($dados);

        // RN-11: nem a semana de origem nem a de destino (motorista/data novos) podem estar fechadas.
        $this->trava->garantirAberta($viagem->motorista_id, $viagem->data_viagem, 'data_viagem');
        $this->trava->garantirAberta($motorista->id, $dados['data_viagem'], 'data_viagem');

        $percentual = (string) $viagem->comissao_percentual;

        if ($motorista->id !== $viagem->motorista_id) {
            $percentual = (string) $motorista->comissao_percentual;
        }

        $informado = $dados['comissao_percentual'] ?? null;
        if ($informado !== null && $informado !== '' && $usuario->can('comissoes.editar')) {
            $percentual = (string) $informado;
        }

        $tarifa = $this->sugestor->paraRota((int) $dados['origem_local_id'], (int) $dados['destino_local_id'], $dados['data_viagem']);

        $viagem->fill([
            'filial_id' => $motorista->filial_id,
            'motorista_id' => $motorista->id,
            'veiculo_id' => (int) $dados['veiculo_id'],
            'origem_local_id' => (int) $dados['origem_local_id'],
            'destino_local_id' => (int) $dados['destino_local_id'],
            'tarifa_rota_id' => $tarifa?->id,
            'data_viagem' => $dados['data_viagem'],
            'valor_frete' => $dados['valor_frete'],
            'comissao_percentual' => $percentual,
            'comissao_valor' => Dinheiro::percentual($dados['valor_frete'], $percentual),
            'status' => $dados['status'] ?? $viagem->status->value,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
        $viagem->updated_by = $usuario->id;
        $viagem->save();

        return $viagem;
    }
}
