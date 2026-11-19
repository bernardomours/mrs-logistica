<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $empresa = fake('pt_BR')->unique()->company();

        return [
            'razao_social' => $empresa.' LTDA',
            'nome_fantasia' => $empresa,
            'cnpj' => fake('pt_BR')->unique()->cnpj(false),
            'telefone' => null,
            'email' => null,
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
