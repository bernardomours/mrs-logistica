<?php

namespace Database\Factories;

use App\Enums\LocalTipo;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Local>
 */
class LocalFactory extends Factory
{
    protected $model = Local::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nome' => fake('pt_BR')->unique()->city().'/'.fake()->randomElement(['RN', 'CE', 'PB']),
            'tipo' => fake()->randomElement(LocalTipo::cases()),
            'cidade' => null,
            'uf' => null,
            'cliente_id' => null,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
