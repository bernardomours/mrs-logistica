<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\TarifaRota;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TarifaRota>
 */
class TarifaRotaFactory extends Factory
{
    protected $model = TarifaRota::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cliente_id' => null,
            'origem_local_id' => Local::factory(),
            'destino_local_id' => Local::factory(),
            'valor_frete' => '4491.69',
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => null,
            'observacoes' => null,
        ];
    }
}
