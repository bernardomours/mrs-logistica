<?php

namespace App\Actions\Lancamentos;

use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\FilialAtual;
use Illuminate\Validation\ValidationException;

/**
 * Regras comuns de viagem e abastecimento sobre motorista e veículo:
 * existem, são ativos (se novos no lançamento), a filial é acessível e **ambos são da mesma filial** (RN-16/RN-19).
 */
class ResolverMotoristaEVeiculo
{
    public function __construct(private readonly FilialAtual $filiais) {}

    /**
     * @param  ?int  $motoristaAtual  motorista já gravado no lançamento (pode estar inativo hoje)
     * @param  ?int  $veiculoAtual  veículo já gravado no lançamento
     * @return array{0: Motorista, 1: Veiculo}
     */
    public function handle(int $motoristaId, int $veiculoId, ?int $motoristaAtual = null, ?int $veiculoAtual = null, ?User $usuario = null): array
    {
        $motorista = Motorista::withoutGlobalScopes()->find($motoristaId);
        $veiculo = Veiculo::withoutGlobalScopes()->find($veiculoId);

        if ($motorista === null) {
            throw ValidationException::withMessages(['motorista_id' => 'Motorista não encontrado.']);
        }

        if ($veiculo === null) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo não encontrado.']);
        }

        if (! $this->filiais->podeAcessar($motorista->filial_id, $usuario)) {
            throw ValidationException::withMessages(['motorista_id' => 'Você não tem acesso à filial deste motorista.']);
        }

        if ($motorista->filial_id !== $veiculo->filial_id) {
            throw ValidationException::withMessages(['veiculo_id' => 'O veículo precisa ser da mesma filial do motorista.']);
        }

        if (! $motorista->ativo && $motorista->id !== $motoristaAtual) {
            throw ValidationException::withMessages(['motorista_id' => 'Este motorista está inativo.']);
        }

        if (! $veiculo->ativo && $veiculo->id !== $veiculoAtual) {
            throw ValidationException::withMessages(['veiculo_id' => 'Este veículo está inativo.']);
        }

        return [$motorista, $veiculo];
    }
}
