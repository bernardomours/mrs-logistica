<?php

namespace Database\Factories;

use App\Models\Abastecimento;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cria motorista e veículo na MESMA filial (RN-19). Os campos derivados (km_anterior, km_rodados, média)
 * ficam nulos: use a Action `CriarAbastecimento` quando precisar da cadeia de hodômetro calculada.
 *
 * @extends Factory<Abastecimento>
 */
class AbastecimentoFactory extends Factory
{
    protected $model = Abastecimento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $filial = Filial::factory()->create();

        return [
            'filial_id' => $filial->id,
            'veiculo_id' => Veiculo::factory()->for($filial),
            'motorista_id' => Motorista::factory()->for($filial),
            'posto_id' => null,
            'data_abastecimento' => '2026-10-01',
            'litros' => '400.000',
            'preco_litro' => '6.800',
            'valor_total' => '2720.00',
            'odometro_km' => '100000.0',
            'tanque_cheio' => true,
            'combustivel' => 'diesel',
            'created_by' => User::factory(),
        ];
    }

    public function daFilial(Filial $filial): static
    {
        return $this->state(fn () => [
            'filial_id' => $filial->id,
            'veiculo_id' => Veiculo::factory()->for($filial),
            'motorista_id' => Motorista::factory()->for($filial),
        ]);
    }
}
