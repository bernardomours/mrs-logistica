<?php

namespace Database\Factories;

use App\Enums\ViagemStatus;
use App\Models\Filial;
use App\Models\Local;
use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cria motorista e veículo na MESMA filial (RN-19). Para usar uma filial específica: `Viagem::factory()->daFilial($filial)`.
 *
 * @extends Factory<Viagem>
 */
class ViagemFactory extends Factory
{
    protected $model = Viagem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $filial = Filial::factory()->create();

        return [
            'filial_id' => $filial->id,
            'motorista_id' => Motorista::factory()->for($filial),
            'veiculo_id' => Veiculo::factory()->for($filial),
            'origem_local_id' => Local::factory(),
            'destino_local_id' => Local::factory(),
            'tarifa_rota_id' => null,
            'data_viagem' => '2026-10-01',
            'valor_frete' => '4491.69',
            'comissao_percentual' => '6.00',
            'comissao_valor' => '269.50',
            'status' => ViagemStatus::Concluida,
            'created_by' => User::factory(),
        ];
    }

    public function daFilial(Filial $filial): static
    {
        return $this->state(fn () => [
            'filial_id' => $filial->id,
            'motorista_id' => Motorista::factory()->for($filial),
            'veiculo_id' => Veiculo::factory()->for($filial),
        ]);
    }

    public function cancelada(): static
    {
        return $this->state(fn () => ['status' => ViagemStatus::Cancelada]);
    }
}
