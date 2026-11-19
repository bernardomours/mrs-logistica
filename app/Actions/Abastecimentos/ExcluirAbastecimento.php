<?php

namespace App\Actions\Abastecimentos;

use App\Models\Abastecimento;
use App\Models\User;
use App\Services\TravaDeSemana;
use Illuminate\Support\Facades\DB;

class ExcluirAbastecimento
{
    public function __construct(
        private readonly RecalcularCadeia $cadeia,
        private readonly TravaDeSemana $trava,
    ) {}

    /** Exclusão lógica; o km inicial do abastecimento seguinte é recalculado (RN-07) — salvo semana fechada (RN-11). */
    public function handle(Abastecimento $abastecimento, User $usuario): void
    {
        $this->trava->garantirAberta($abastecimento->motorista_id, $abastecimento->data_abastecimento, 'data_abastecimento');
        $this->trava->garantirSucessorLivre($abastecimento->veiculo_id, (string) $abastecimento->odometro_km, $abastecimento->id);

        DB::transaction(function () use ($abastecimento, $usuario) {
            $abastecimento->updated_by = $usuario->id;
            $abastecimento->saveQuietly();
            $abastecimento->delete();

            $this->cadeia->handle($abastecimento->veiculo_id);
        });
    }
}
