<?php

namespace App\Services;

use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Scopes\FilialScope;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Support\Texto;

/**
 * Dados pessoais do portal (RN-21): o que o administrador cadastrou para o motorista, a filial e o veículo ligado
 * a ele. Mostra só o necessário (sem observações internas nem dados de outros motoristas). O motorista é sempre
 * o do usuário logado.
 */
class PerfilDoMotorista
{
    public function motoristaDoUsuario(User $usuario): ?Motorista
    {
        return Motorista::withoutGlobalScopes()->with('filial')->where('user_id', $usuario->id)->first();
    }

    /**
     * @return array{
     *     dados: array<string, mixed>,
     *     filial: array<string, ?string>,
     *     veiculos: array<int, array<string, ?string>>,
     *     veiculoDoCadastro: bool
     * }
     */
    public function para(Motorista $motorista): array
    {
        $filial = $motorista->filial;

        $vinculados = Veiculo::withoutGlobalScope(FilialScope::class)
            ->where('motorista_padrao_id', $motorista->id)->where('ativo', true)->orderBy('placa')->get();

        $veiculos = $vinculados->isNotEmpty() ? $vinculados : $this->ultimoUsado($motorista);

        return [
            'dados' => [
                'nome' => $motorista->nome,
                'cpf' => Texto::cpf($motorista->cpf),
                'telefone' => $motorista->telefone,
                'cnh_numero' => $motorista->cnh_numero,
                'cnh_categoria' => $motorista->cnh_categoria,
                'cnh_validade' => $motorista->cnh_validade,
                'cnh_vencida' => $motorista->cnhVencida(),
                'data_admissao' => $motorista->data_admissao,
                'comissao' => (string) $motorista->comissao_percentual,
                'ativo' => (bool) $motorista->ativo,
            ],
            'filial' => [
                'nome' => $filial->nome,
                'codigo' => $filial->codigo,
                'cidade' => $filial->cidade,
                'uf' => $filial->uf,
                'telefone' => $filial->telefone,
            ],
            'veiculos' => $veiculos->map(fn (Veiculo $v) => [
                'placa' => $v->placa_formatada,
                'tipo' => $v->tipo?->rotulo(),
                'marca' => $v->marca,
                'modelo' => $v->modelo,
                'ano' => $v->ano_fabricacao !== null ? (string) $v->ano_fabricacao : null,
                'tanque' => $v->capacidade_tanque_l !== null ? (string) $v->capacidade_tanque_l : null,
            ])->values()->all(),
            'veiculoDoCadastro' => $vinculados->isNotEmpty(),
        ];
    }

    /** Sem veículo padrão no cadastro: o último caminhão que o motorista usou em viagem ou abastecimento. */
    private function ultimoUsado(Motorista $motorista)
    {
        $viagem = Viagem::withoutGlobalScope(FilialScope::class)->where('motorista_id', $motorista->id)
            ->orderByDesc('data_viagem')->orderByDesc('id')->first(['veiculo_id', 'data_viagem']);
        $abastecimento = Abastecimento::withoutGlobalScope(FilialScope::class)->where('motorista_id', $motorista->id)
            ->orderByDesc('data_abastecimento')->orderByDesc('id')->first(['veiculo_id', 'data_abastecimento']);

        $id = match (true) {
            $viagem && $abastecimento => $viagem->data_viagem->greaterThanOrEqualTo($abastecimento->data_abastecimento) ? $viagem->veiculo_id : $abastecimento->veiculo_id,
            (bool) $viagem => $viagem->veiculo_id,
            (bool) $abastecimento => $abastecimento->veiculo_id,
            default => null,
        };

        return $id === null
            ? collect()
            : Veiculo::withoutGlobalScope(FilialScope::class)->whereKey($id)->get();
    }
}
