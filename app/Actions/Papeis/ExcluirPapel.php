<?php

namespace App\Actions\Papeis;

use App\Models\User;
use App\Support\PapeisDoSistema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Exclui um papel personalizado que ninguém usa. Os papéis padrão do sistema não são excluídos. */
class ExcluirPapel
{
    public function handle(User $ator, Role $papel): void
    {
        if (! $ator->can('papeis.gerenciar') || PapeisDoSistema::ehDoSistema($papel->name)) {
            throw new AuthorizationException('Este papel não pode ser excluído.');
        }

        if ($papel->users()->exists()) {
            throw ValidationException::withMessages(['papel' => 'Há usuários com este papel. Troque o papel deles antes de excluir.']);
        }

        DB::transaction(function () use ($ator, $papel) {
            activity('papeis')->performedOn($papel)->causedBy($ator)
                ->withProperties(['nome' => $papel->name, 'permissoes' => $papel->permissions()->pluck('name')->all()])
                ->log('Papel excluído');

            $papel->delete();
        });
    }
}
