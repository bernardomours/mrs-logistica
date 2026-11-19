<?php

namespace App\Actions\Abastecimentos;

use App\Actions\Lancamentos\ResolverMotoristaEVeiculo;
use App\Models\Abastecimento;
use App\Models\Scopes\FilialScope;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\TravaDeSemana;
use App\Services\TripeAbastecimento;
use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CriarAbastecimento
{
    public function __construct(
        private readonly ResolverMotoristaEVeiculo $vinculos,
        private readonly TripeAbastecimento $tripe,
        private readonly RecalcularCadeia $cadeia,
        private readonly TravaDeSemana $trava,
    ) {}

    /**
     * @param  array<string, mixed>  $dados  veiculo_id, motorista_id, data_abastecimento, odometro_km e 2 de 3 entre
     *                                       litros/preco_litro/valor_total; opcionais: posto_id, km_anterior (só vale no
     *                                       1º abastecimento do veículo sem hodômetro inicial), tanque_cheio, combustivel, nota_fiscal, observacoes
     */
    public function handle(array $dados, User $usuario): Abastecimento
    {
        [$motorista, $veiculo] = $this->vinculos->handle((int) $dados['motorista_id'], (int) $dados['veiculo_id'], usuario: $usuario);

        $valores = $this->tripe->completar($dados['litros'] ?? null, $dados['preco_litro'] ?? null, $dados['valor_total'] ?? null);
        $this->validarHodometro($veiculo, (string) $dados['odometro_km'], $dados['km_anterior'] ?? null);

        // RN-11: semana do motorista fechada, ou cadeia que alteraria o abastecimento seguinte de uma semana fechada.
        $this->trava->garantirAberta($motorista->id, $dados['data_abastecimento'], 'data_abastecimento');
        $this->trava->garantirSucessorLivre($veiculo->id, (string) $dados['odometro_km']);

        return DB::transaction(function () use ($dados, $usuario, $motorista, $veiculo, $valores) {
            $abastecimento = new Abastecimento($valores + [
                'filial_id' => $motorista->filial_id,
                'veiculo_id' => $veiculo->id,
                'motorista_id' => $motorista->id,
                'posto_id' => $dados['posto_id'] ?? null,
                'data_abastecimento' => $dados['data_abastecimento'],
                'odometro_km' => $dados['odometro_km'],
                'km_anterior' => $dados['km_anterior'] ?? null,
                'tanque_cheio' => $dados['tanque_cheio'] ?? true,
                'combustivel' => $dados['combustivel'] ?? 'diesel',
                'nota_fiscal' => $dados['nota_fiscal'] ?? null,
                'observacoes' => $dados['observacoes'] ?? null,
            ]);
            $abastecimento->created_by = $usuario->id;
            $abastecimento->save();

            $this->cadeia->handle($veiculo->id);

            return $abastecimento->refresh();
        });
    }

    /**
     * O hodômetro não pode repetir uma leitura do mesmo veículo nem ficar abaixo da referência anterior (RN-07).
     */
    public function validarHodometro(Veiculo $veiculo, string $odometro, ?string $kmAnteriorManual, ?int $ignorarId = null): void
    {
        $repetido = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->where('veiculo_id', $veiculo->id)->where('odometro_km', $odometro)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->exists();

        if ($repetido) {
            throw ValidationException::withMessages(['odometro_km' => 'Já existe um abastecimento deste veículo com este hodômetro.']);
        }

        $referencia = $this->cadeia->referenciaPara($veiculo, $odometro, $ignorarId) ?? $kmAnteriorManual;

        if ($referencia !== null && Dinheiro::comparar($odometro, $referencia, 1) < 0) {
            throw ValidationException::withMessages([
                'odometro_km' => 'O hodômetro ('.number_format((float) $odometro, 1, ',', '.').') não pode ser menor que o km inicial ('.number_format((float) $referencia, 1, ',', '.').').',
            ]);
        }
    }
}
