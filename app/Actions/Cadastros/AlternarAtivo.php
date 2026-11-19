<?php

namespace App\Actions\Cadastros;

use App\Models\Motorista;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Ativa/desativa qualquer cadastro com coluna `ativo`. Cadastros não são excluídos (RN-17):
 * o model pode expor `motivoParaNaoDesativar(): ?string` para bloquear a desativação.
 */
class AlternarAtivo
{
    public function handle(Model $cadastro): Model
    {
        if ($cadastro->ativo && method_exists($cadastro, 'motivoParaNaoDesativar')) {
            $motivo = $cadastro->motivoParaNaoDesativar();

            if ($motivo !== null) {
                throw ValidationException::withMessages(['ativo' => $motivo]);
            }
        }

        $cadastro->update(['ativo' => ! $cadastro->ativo]);

        // Motorista inativo não entra no portal (RN-21); reativar libera o acesso de novo.
        if ($cadastro instanceof Motorista && $cadastro->user_id !== null) {
            User::query()->whereKey($cadastro->user_id)->update(['ativo' => $cadastro->ativo]);
        }

        return $cadastro;
    }
}
