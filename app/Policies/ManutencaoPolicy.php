<?php

namespace App\Policies;

use App\Models\Manutencao;
use App\Models\User;
use App\Services\TravaDeSemana;
use Illuminate\Database\Eloquent\Model;

/**
 * Registrar e editar manutenção exige também `manutencoes.ver_valores` (o formulário trabalha com valores).
 * Manutenção de motorista em semana fechada fica travada (RN-20/RN-11).
 */
class ManutencaoPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'manutencoes';
    }

    private function semanaFechada(Manutencao $manutencao): bool
    {
        return $manutencao->motorista_id !== null
            && app(TravaDeSemana::class)->semanaFechada($manutencao->motorista_id, $manutencao->data_manutencao);
    }

    public function create(User $user): bool
    {
        return parent::create($user) && $user->can('manutencoes.ver_valores');
    }

    public function update(User $user, Model $modelo): bool
    {
        return parent::update($user, $modelo) && $user->can('manutencoes.ver_valores') && ! $this->semanaFechada($modelo);
    }

    public function delete(User $user, Model $modelo): bool
    {
        return $user->can('manutencoes.excluir') && $this->acessa($modelo) && ! $this->semanaFechada($modelo);
    }
}
