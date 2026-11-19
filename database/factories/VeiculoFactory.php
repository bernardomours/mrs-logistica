<?php

namespace Database\Factories;

use App\Enums\VeiculoTipo;
use App\Models\Filial;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Veiculo>
 */
class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'filial_id' => Filial::factory(),
            'placa' => strtoupper(fake()->unique()->lexify('???')).fake()->numerify('#').fake()->randomElement(['A', 'B', '1', '2']).fake()->numerify('##'),
            'tipo' => VeiculoTipo::Cavalo,
            'marca' => null,
            'modelo' => null,
            'ano_fabricacao' => null,
            'renavam' => null,
            'capacidade_tanque_l' => null,
            'odometro_inicial' => null,
            'consumo_referencia_km_l' => null,
            'motorista_padrao_id' => null,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
