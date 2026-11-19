<?php

namespace App\Actions\Manutencoes;

use App\Enums\ManutencaoTipo;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\Scopes\FilialScope;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\FilialAtual;
use App\Services\TravaDeSemana;
use App\Support\Dinheiro;
use Illuminate\Validation\ValidationException;

/** RN-20: manutenção por veículo (motorista opcional); o total é peças + mão de obra. Não mexe em hodômetro nem no líquido. */
class RegistrarManutencao
{
    public function __construct(
        private readonly FilialAtual $filiais,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  veiculo_id, data_manutencao, tipo, descricao, valor_pecas, valor_mao_obra;
     *                                       opcionais: motorista_id, oficina, odometro_km, nota_fiscal, proxima_km, proxima_data, observacoes
     */
    public function handle(array $dados, User $usuario): Manutencao
    {
        [$veiculo, $motorista] = $this->vinculos($dados, $usuario);
        $valores = $this->valores($dados);

        if ($motorista !== null) {
            $this->trava->garantirAberta($motorista->id, $dados['data_manutencao'], 'data_manutencao');
        }

        $manutencao = new Manutencao($valores + $this->campos($dados) + [
            'filial_id' => $veiculo->filial_id,
            'veiculo_id' => $veiculo->id,
            'motorista_id' => $motorista?->id,
        ]);
        $manutencao->created_by = $usuario->id;
        $manutencao->save();

        return $manutencao;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array{0: Veiculo, 1: ?Motorista}
     */
    public function vinculos(array $dados, User $usuario, ?Manutencao $atual = null): array
    {
        $veiculo = Veiculo::withoutGlobalScope(FilialScope::class)->find((int) $dados['veiculo_id']);

        if ($veiculo === null) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo não encontrado.']);
        }

        if (! $this->filiais->podeAcessar($veiculo->filial_id, $usuario)) {
            throw ValidationException::withMessages(['veiculo_id' => 'Você não tem acesso à filial deste veículo.']);
        }

        if (! $veiculo->ativo && $veiculo->id !== $atual?->veiculo_id) {
            throw ValidationException::withMessages(['veiculo_id' => 'Este veículo está inativo.']);
        }

        $motorista = null;

        if (! empty($dados['motorista_id'])) {
            $motorista = Motorista::withoutGlobalScope(FilialScope::class)->find((int) $dados['motorista_id']);

            if ($motorista === null) {
                throw ValidationException::withMessages(['motorista_id' => 'Motorista não encontrado.']);
            }

            if ($motorista->filial_id !== $veiculo->filial_id) {
                throw ValidationException::withMessages(['motorista_id' => 'O motorista precisa ser da mesma filial do veículo.']);
            }

            if (! $motorista->ativo && $motorista->id !== $atual?->motorista_id) {
                throw ValidationException::withMessages(['motorista_id' => 'Este motorista está inativo.']);
            }
        }

        return [$veiculo, $motorista];
    }

    /**
     * Total = peças + mão de obra (RN-20), sempre calculado aqui.
     *
     * @param  array<string, mixed>  $dados
     * @return array{valor_pecas: string, valor_mao_obra: string, valor_total: string}
     */
    public function valores(array $dados): array
    {
        $pecas = (string) ($dados['valor_pecas'] ?? '0');
        $maoDeObra = (string) ($dados['valor_mao_obra'] ?? '0');

        foreach (['valor_pecas' => $pecas, 'valor_mao_obra' => $maoDeObra] as $campo => $valor) {
            if (Dinheiro::comparar($valor, '0') < 0) {
                throw ValidationException::withMessages([$campo => 'O valor não pode ser negativo.']);
            }
        }

        $total = Dinheiro::somar($pecas, $maoDeObra);

        if (Dinheiro::comparar($total, '0') <= 0) {
            throw ValidationException::withMessages(['valor_pecas' => 'Informe o valor das peças e/ou da mão de obra.']);
        }

        return ['valor_pecas' => $pecas, 'valor_mao_obra' => $maoDeObra, 'valor_total' => $total];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function campos(array $dados): array
    {
        if (ManutencaoTipo::tryFrom((string) ($dados['tipo'] ?? '')) === null) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de manutenção inválido.']);
        }

        if (! empty($dados['proxima_data']) && $dados['proxima_data'] < $dados['data_manutencao']) {
            throw ValidationException::withMessages(['proxima_data' => 'A próxima manutenção não pode ser antes desta.']);
        }

        if (! empty($dados['proxima_km']) && ! empty($dados['odometro_km']) && Dinheiro::comparar((string) $dados['proxima_km'], (string) $dados['odometro_km'], 1) <= 0) {
            throw ValidationException::withMessages(['proxima_km' => 'O km da próxima manutenção precisa ser maior que o hodômetro desta.']);
        }

        return [
            'data_manutencao' => $dados['data_manutencao'],
            'tipo' => $dados['tipo'],
            'descricao' => $dados['descricao'],
            'oficina' => $dados['oficina'] ?? null,
            'odometro_km' => $dados['odometro_km'] ?? null,
            'nota_fiscal' => $dados['nota_fiscal'] ?? null,
            'proxima_km' => $dados['proxima_km'] ?? null,
            'proxima_data' => $dados['proxima_data'] ?? null,
            'observacoes' => $dados['observacoes'] ?? null,
        ];
    }
}
