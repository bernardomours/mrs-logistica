<?php

namespace App\Policies;

use App\Models\Abastecimento;
use App\Models\User;
use App\Services\TravaDeSemana;
use Illuminate\Database\Eloquent\Model;

class AbastecimentoPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'abastecimentos';
    }

    /** Semana fechada do motorista trava o abastecimento (RN-11). */
    private function semanaFechada(Abastecimento $abastecimento): bool
    {
        return app(TravaDeSemana::class)->semanaFechada($abastecimento->motorista_id, $abastecimento->data_abastecimento);
    }

    public function update(User $user, Model $modelo): bool
    {
        return parent::update($user, $modelo) && ! $this->semanaFechada($modelo);
    }

    public function delete(User $user, Model $modelo): bool
    {
        return $user->can('abastecimentos.excluir') && $this->acessa($modelo) && ! $this->semanaFechada($modelo);
    }
}
