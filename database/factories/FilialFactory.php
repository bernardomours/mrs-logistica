<?php

namespace Database\Factories;

use App\Models\Filial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Filial>
 */
class FilialFactory extends Factory
{
    protected $model = Filial::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $cidade = fake()->city();

        return [
            'nome' => 'Filial '.$cidade.' '.fake()->unique()->numerify('##'),
            'codigo' => strtoupper(fake()->unique()->lexify('???')),
            'cnpj' => null,
            'cidade' => $cidade,
            'uf' => fake()->randomElement(['RN', 'CE', 'PB', 'PE']),
            'telefone' => null,
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
