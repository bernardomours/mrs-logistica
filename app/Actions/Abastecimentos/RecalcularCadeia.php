<?php

namespace App\Actions\Abastecimentos;

use App\Models\Abastecimento;
use App\Models\Scopes\FilialScope;
use App\Models\Veiculo;
use App\Support\Dinheiro;

/**
 * RN-07/RN-08 — cadeia de hodômetro de UM veículo, ordenada por `odometro_km` (o hodômetro só cresce).
 *
 *  - km_anterior = hodômetro do abastecimento imediatamente anterior; para o 1º, o `odometro_inicial` do veículo
 *    (ou, sem ele, o km inicial digitado à mão, que fica preservado);
 *  - km_rodados  = odometro_km − km_anterior (nulo sem referência ou se negativo);
 *  - media_km_l  = km_rodados ÷ litros (4 casas; nula se km_rodados ≤ 0).
 *
 * Recalcula a cadeia toda (barato: dezenas/centenas de linhas por veículo) e grava só o que mudou,
 * sem auditar (são colunas derivadas).
 */
class RecalcularCadeia
{
    public function handle(int $veiculoId): void
    {
        $veiculo = Veiculo::withoutGlobalScopes()->find($veiculoId);

        if ($veiculo === null) {
            return;
        }

        $anterior = null;

        Abastecimento::withoutGlobalScope(FilialScope::class)
            ->where('veiculo_id', $veiculoId)
            ->orderBy('odometro_km')->orderBy('id')
            ->get()
            ->each(function (Abastecimento $abastecimento, int $indice) use ($veiculo, &$anterior) {
                $referencia = $indice === 0
                    ? ($veiculo->odometro_inicial !== null ? (string) $veiculo->odometro_inicial : $abastecimento->km_anterior)
                    : $anterior;

                $rodados = $referencia !== null ? Dinheiro::subtrair((string) $abastecimento->odometro_km, (string) $referencia, 1) : null;

                if ($rodados !== null && Dinheiro::comparar($rodados, '0', 1) < 0) {
                    $rodados = null;
                }

                $media = $rodados !== null && Dinheiro::comparar($rodados, '0', 1) > 0
                    ? Dinheiro::dividir($rodados, (string) $abastecimento->litros, 4)
                    : null;

                $abastecimento->km_anterior = $referencia;
                $abastecimento->km_rodados = $rodados;
                $abastecimento->media_km_l = $media;

                if ($abastecimento->isDirty()) {
                    $abastecimento->saveQuietly();
                }

                $anterior = (string) $abastecimento->odometro_km;
            });
    }

    /**
     * Hodômetro de referência para um novo `odometro_km` (maior leitura menor que ele, ou o inicial do veículo).
     * Usado na validação antes de gravar.
     */
    public function referenciaPara(Veiculo $veiculo, string $odometro, ?int $ignorarId = null): ?string
    {
        $anterior = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->where('veiculo_id', $veiculo->id)
            ->where('odometro_km', '<', $odometro)
            ->when($ignorarId !== null, fn ($q) => $q->whereKeyNot($ignorarId))
            ->max('odometro_km');

        return $anterior !== null ? (string) $anterior : ($veiculo->odometro_inicial !== null ? (string) $veiculo->odometro_inicial : null);
    }
}
