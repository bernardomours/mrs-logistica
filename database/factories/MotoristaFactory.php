<?php

namespace Database\Factories;

use App\Models\Filial;
use App\Models\Motorista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Motorista>
 */
class MotoristaFactory extends Factory
{
    protected $model = Motorista::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'filial_id' => Filial::factory(),
            'nome' => fake('pt_BR')->name(),
            'cpf' => fake('pt_BR')->unique()->cpf(false),
            'cnh_numero' => null,
            'cnh_categoria' => 'E',
            'cnh_validade' => now()->addYears(2)->toDateString(),
            'telefone' => null,
            'comissao_percentual' => '6.00',
            'data_admissao' => null,
            'ativo' => true,
            'observacoes' => null,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
