<?php

namespace App\Actions\Fechamentos;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Models\User;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** RN-11: grava o snapshot da semana do motorista e, com isso, trava os lançamentos dela. */
class FecharSemana
{
    use AutorizaFechamento;

    public function __construct(private readonly ResumoSemanal $resumos) {}

    public function handle(Motorista $motorista, SemanaIso $semana, User $usuario, ?string $observacoes = null): FechamentoSemanal
    {
        $this->exigirPermissaoEFilial($usuario, 'fechamentos.fechar', $motorista->filial_id);

        if ($semana->inicio->greaterThan(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['semana' => 'Não é possível fechar uma semana futura.']);
        }

        $resumo = $this->resumos->paraMotorista($motorista, $semana);

        if ($resumo->estaFechada()) {
            throw ValidationException::withMessages(['semana' => 'Esta semana já está fechada.']);
        }

        if ($resumo->semLancamentos()) {
            throw ValidationException::withMessages(['semana' => 'A semana não tem viagens nem abastecimentos para fechar.']);
        }

        if ($resumo->viagensPendentes > 0) {
            throw ValidationException::withMessages([
                'semana' => "Há {$resumo->viagensPendentes} viagem(ns) planejada(s) ou em trânsito nesta semana. Conclua ou cancele antes de fechar.",
            ]);
        }

        if ($resumo->abastecimentos->contains(fn ($a) => bccomp((string) $a->odometro_km, '0', 1) <= 0)) {
            throw ValidationException::withMessages(['semana' => 'Há abastecimento com hodômetro inválido nesta semana. Corrija antes de fechar.']);
        }

        try {
            return DB::transaction(function () use ($motorista, $semana, $usuario, $observacoes, $resumo) {
                $fechamento = new FechamentoSemanal([
                    'filial_id' => $motorista->filial_id,
                    'motorista_id' => $motorista->id,
                    'ano_iso' => $semana->ano,
                    'semana_iso' => $semana->semana,
                    'data_inicio' => $semana->inicio->toDateString(),
                    'data_fim' => $semana->fim->toDateString(),
                    'status' => FechamentoStatus::Fechado,
                    'qtd_viagens' => $resumo->qtdViagens,
                    'total_faturamento' => $resumo->faturamento,
                    'total_comissao' => $resumo->comissao,
                    'valor_a_pagar' => $resumo->valorAPagar,
                    'total_abastecimento' => $resumo->abastecimento,
                    'total_liquido' => $resumo->liquido,
                    'total_litros' => $resumo->litros,
                    'total_km' => $resumo->km,
                    'media_km_l_simples' => $resumo->mediaSimples,
                    'media_km_l_ponderada' => $resumo->mediaPonderada,
                    'observacoes' => $observacoes !== null && trim($observacoes) !== '' ? trim($observacoes) : null,
                ]);
                $fechamento->fechado_por = $usuario->id;
                $fechamento->fechado_em = now();
                $fechamento->save();

                return $fechamento;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['semana' => 'Esta semana acabou de ser fechada por outro usuário.']);
        }
    }
}
