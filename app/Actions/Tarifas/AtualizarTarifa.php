<?php

namespace App\Actions\Tarifas;

use App\Models\TarifaRota;

class AtualizarTarifa
{
    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(TarifaRota $tarifa, array $dados): TarifaRota
    {
        CriarTarifa::validarVigencia($dados, $tarifa);

        $tarifa->update($dados);

        return $tarifa;
    }
}
