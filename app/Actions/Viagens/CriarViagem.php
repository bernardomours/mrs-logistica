<?php

namespace App\Actions\Viagens;

use App\Actions\Lancamentos\ResolverMotoristaEVeiculo;
use App\Enums\ViagemStatus;
use App\Models\User;
use App\Models\Viagem;
use App\Services\SugestorDeFrete;
use App\Services\TravaDeSemana;
use App\Support\Dinheiro;
use Illuminate\Validation\ValidationException;

class CriarViagem
{
    public function __construct(
        private readonly ResolverMotoristaEVeiculo $vinculos,
        private readonly SugestorDeFrete $sugestor,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  motorista_id, veiculo_id, origem_local_id, destino_local_id, data_viagem,
     *                                       valor_frete, status e opcionais; `comissao_percentual` só vale para quem tem `comissoes.editar`.
     */
    public function handle(array $dados, User $usuario): Viagem
    {
        [$motorista] = $this->vinculos->handle((int) $dados['motorista_id'], (int) $dados['veiculo_id'], usuario: $usuario);

        self::validarRota($dados);
        $this->trava->garantirAberta($motorista->id, $dados['data_viagem'], 'data_viagem');

        $percentual = $this->percentual($dados, (string) $motorista->comissao_percentual, $usuario);
        $tarifa = $this->sugestor->paraRota((int) $dados['origem_local_id'], (int) $dados['destino_local_id'], $dados['data_viagem']);

        $viagem = new Viagem([
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
            'status' => $dados['status'] ?? ViagemStatus::Concluida->value,
            'observacoes' => $dados['observacoes'] ?? null,
        ]);
        $viagem->created_by = $usuario->id;
        $viagem->save();

        return $viagem;
    }

    /** @param  array<string, mixed>  $dados */
    public static function validarRota(array $dados): void
    {
        if ((int) $dados['origem_local_id'] === (int) $dados['destino_local_id']) {
            throw ValidationException::withMessages(['destino_local_id' => 'Origem e destino não podem ser iguais.']);
        }

        if (Dinheiro::comparar((string) $dados['valor_frete'], '0') < 0) {
            throw ValidationException::withMessages(['valor_frete' => 'O valor do frete não pode ser negativo.']);
        }
    }

    /**
     * RN-02: o percentual nasce do cadastro do motorista (snapshot); só quem tem `comissoes.editar` pode ajustá-lo.
     *
     * @param  array<string, mixed>  $dados
     */
    private function percentual(array $dados, string $doMotorista, User $usuario): string
    {
        $informado = $dados['comissao_percentual'] ?? null;

        return $informado !== null && $informado !== '' && $usuario->can('comissoes.editar')
            ? (string) $informado
            : $doMotorista;
    }
}
