<?php

namespace Database\Factories;

use App\Models\Posto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Posto>
 */
class PostoFactory extends Factory
{
    protected $model = Posto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nome' => 'Posto '.fake('pt_BR')->unique()->lastName().' '.fake()->numerify('##'),
            'cnpj' => null,
            'cidade' => fake('pt_BR')->city(),
            'uf' => fake()->randomElement(['RN', 'CE', 'PB']),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
