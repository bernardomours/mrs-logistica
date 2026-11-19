<?php

namespace Database\Factories;

use App\Enums\ManutencaoTipo;
use App\Models\Filial;
use App\Models\Manutencao;
use App\Models\User;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Cria o veículo na filial da manutenção. Para outra filial: `Manutencao::factory()->daFilial($filial)`.
 *
 * @extends Factory<Manutencao>
 */
class ManutencaoFactory extends Factory
{
    protected $model = Manutencao::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $filial = Filial::factory()->create();

        return [
            'filial_id' => $filial->id,
            'veiculo_id' => Veiculo::factory()->for($filial),
            'motorista_id' => null,
            'data_manutencao' => '2026-10-05',
            'tipo' => ManutencaoTipo::Preventiva,
            'descricao' => 'Troca de óleo e filtros',
            'oficina' => 'Oficina Central',
            'odometro_km' => '663000.0',
            'valor_pecas' => '800.00',
            'valor_mao_obra' => '200.00',
            'valor_total' => '1000.00',
            'created_by' => User::factory(),
        ];
    }

    public function daFilial(Filial $filial): static
    {
        return $this->state(fn () => ['filial_id' => $filial->id, 'veiculo_id' => Veiculo::factory()->for($filial)]);
    }
}
