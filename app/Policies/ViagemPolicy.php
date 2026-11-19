<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Viagem;
use App\Services\TravaDeSemana;
use Illuminate\Database\Eloquent\Model;

class ViagemPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'viagens';
    }

    /** Semana fechada do motorista trava a viagem (RN-11). */
    private function semanaFechada(Viagem $viagem): bool
    {
        return app(TravaDeSemana::class)->semanaFechada($viagem->motorista_id, $viagem->data_viagem);
    }

    /** Excluir (exclusão lógica) é uma permissão própria (RN-12). */
    public function delete(User $user, Model $modelo): bool
    {
        return $user->can('viagens.excluir') && $this->acessa($modelo) && ! $this->semanaFechada($modelo);
    }

    /** Cancelar preserva o histórico; só viagens ainda não canceladas. */
    public function cancelar(User $user, Viagem $viagem): bool
    {
        return $user->can('viagens.cancelar') && $this->acessa($viagem) && ! $viagem->estaCancelada() && ! $this->semanaFechada($viagem);
    }

    /** Viagem cancelada fica somente para consulta, e a de semana fechada também. */
    public function update(User $user, Model $modelo): bool
    {
        return parent::update($user, $modelo) && ! $modelo->estaCancelada() && ! $this->semanaFechada($modelo);
    }
}
