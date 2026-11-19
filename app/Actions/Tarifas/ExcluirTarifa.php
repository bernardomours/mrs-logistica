<?php

namespace App\Actions\Tarifas;

use App\Models\TarifaRota;

class ExcluirTarifa
{
    /** Exclusão lógica: viagens já lançadas guardam o valor próprio (snapshot) e não são afetadas. */
    public function handle(TarifaRota $tarifa): void
    {
        $tarifa->delete();
    }
}
