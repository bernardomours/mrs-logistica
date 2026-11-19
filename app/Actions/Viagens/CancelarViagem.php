<?php

namespace App\Actions\Viagens;

use App\Enums\ViagemStatus;
use App\Models\User;
use App\Models\Viagem;
use App\Services\TravaDeSemana;

/** Viagem lançada por engano: cancela em vez de apagar, preservando o histórico (RN-12). */
class CancelarViagem
{
    public function __construct(private readonly TravaDeSemana $trava) {}

    public function handle(Viagem $viagem, User $usuario): Viagem
    {
        if (! $viagem->estaCancelada()) {
            $this->trava->garantirAberta($viagem->motorista_id, $viagem->data_viagem, 'data_viagem');

            $viagem->status = ViagemStatus::Cancelada;
            $viagem->updated_by = $usuario->id;
            $viagem->save();
        }

        return $viagem;
    }
}
