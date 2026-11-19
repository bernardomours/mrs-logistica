<?php

namespace Database\Factories;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\User;
use App\Support\SemanaIso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Semana 40/2026 (28/09 a 04/10) por padrão. Para outra semana: `->daSemana(SemanaIso::de(2026, 42))`.
 *
 * @extends Factory<FechamentoSemanal>
 */
class FechamentoSemanalFactory extends Factory
{
    protected $model = FechamentoSemanal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $filial = Filial::factory()->create();
        $semana = SemanaIso::de(2026, 40);

        return [
            'filial_id' => $filial->id,
            'motorista_id' => Motorista::factory()->for($filial),
            'ano_iso' => $semana->ano,
            'semana_iso' => $semana->semana,
            'data_inicio' => $semana->inicio->toDateString(),
            'data_fim' => $semana->fim->toDateString(),
            'status' => FechamentoStatus::Fechado,
            'qtd_viagens' => 3,
            'total_faturamento' => '20153.65',
            'total_comissao' => '1209.22',
            'valor_a_pagar' => '1209.22',
            'total_abastecimento' => '7516.00',
            'total_liquido' => '11428.43',
            'total_litros' => '1100.000',
            'total_km' => '1683.0',
            'media_km_l_simples' => '2.4888',
            'media_km_l_ponderada' => '2.4043',
            'fechado_por' => User::factory(),
            'fechado_em' => now(),
        ];
    }

    public function daSemana(SemanaIso $semana): static
    {
        return $this->state(fn () => [
            'ano_iso' => $semana->ano,
            'semana_iso' => $semana->semana,
            'data_inicio' => $semana->inicio->toDateString(),
            'data_fim' => $semana->fim->toDateString(),
        ]);
    }

    public function doMotorista(Motorista $motorista): static
    {
        return $this->state(fn () => ['filial_id' => $motorista->filial_id, 'motorista_id' => $motorista->id]);
    }

    public function pago(): static
    {
        return $this->state(fn () => [
            'status' => FechamentoStatus::Pago,
            'pago_por' => User::factory(),
            'pago_em' => now(),
        ]);
    }
}
