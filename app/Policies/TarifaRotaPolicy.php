<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TarifaRotaPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'tarifas';
    }

    /** Importar planilha de tarifas (cria/atualiza várias de uma vez). */
    public function importar(User $user): bool
    {
        return $user->can('tarifas.importar');
    }

    /** Tarifa não tem histórico financeiro próprio: pode ser excluída (exclusão lógica). */
    public function delete(User $user, Model $modelo): bool
    {
        return $user->can('tarifas.excluir');
    }
}
