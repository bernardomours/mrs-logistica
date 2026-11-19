<?php

namespace App\Services;

use App\Models\TarifaRota;
use DateTimeInterface;

/**
 * Sugere o frete de uma viagem a partir da tarifa de rota vigente na data (RN-10).
 * Tarifa de cliente específico (reservada) tem prioridade sobre a genérica.
 */
class SugestorDeFrete
{
    public function paraRota(int $origemId, int $destinoId, DateTimeInterface|string $data, ?int $clienteId = null): ?TarifaRota
    {
        return TarifaRota::query()
            ->vigentesEm($data)
            ->where('origem_local_id', $origemId)
            ->where('destino_local_id', $destinoId)
            ->where(fn ($q) => $q->whereNull('cliente_id')->when($clienteId !== null, fn ($c) => $c->orWhere('cliente_id', $clienteId)))
            ->orderByRaw('cliente_id is null')
            ->orderByDesc('vigencia_inicio')
            ->first();
    }
}
