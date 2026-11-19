<?php

namespace App\Actions\Manutencoes;

use App\Models\Manutencao;
use App\Models\User;
use App\Services\TravaDeSemana;

/** Exclusão lógica; a auditoria registra quem excluiu. Não vale para manutenção de motorista em semana fechada. */
class ExcluirManutencao
{
    public function __construct(private readonly TravaDeSemana $trava) {}

    public function handle(Manutencao $manutencao, User $usuario): void
    {
        if ($manutencao->motorista_id !== null) {
            $this->trava->garantirAberta($manutencao->motorista_id, $manutencao->data_manutencao, 'data_manutencao');
        }

        $manutencao->updated_by = $usuario->id;
        $manutencao->saveQuietly();
        $manutencao->delete();
    }
}
