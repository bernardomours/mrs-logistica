<?php

namespace App\Actions\Viagens;

use App\Models\User;
use App\Models\Viagem;
use App\Services\TravaDeSemana;

/** Exclusão lógica (RN-12); a auditoria registra quem excluiu. Não vale em semana fechada (RN-11). */
class ExcluirViagem
{
    public function __construct(private readonly TravaDeSemana $trava) {}

    public function handle(Viagem $viagem, User $usuario): void
    {
        $this->trava->garantirAberta($viagem->motorista_id, $viagem->data_viagem, 'data_viagem');

        $viagem->updated_by = $usuario->id;
        $viagem->saveQuietly();
        $viagem->delete();
    }
}
