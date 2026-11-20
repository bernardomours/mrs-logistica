<?php

namespace App\Actions\Papeis;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Cria um papel personalizado com as permissões escolhidas (só as que o próprio ator possui). */
class CriarPapel
{
    use ValidaPermissoesDoPapel;

    /**
     * @param  array<int, string>  $permissoes
     */
    public function handle(User $ator, string $nome, array $permissoes): Role
    {
        if (! $ator->can('papeis.gerenciar')) {
            throw new AuthorizationException('Você não tem permissão para gerenciar papéis.');
        }

        $nome = $this->nomeValido($nome);
        $permissoes = $this->permissoesValidas($permissoes);
        $this->garantirQueAtorPossui($ator, $permissoes);

        return DB::transaction(function () use ($ator, $nome, $permissoes) {
            $papel = Role::create(['name' => $nome, 'guard_name' => 'web']);
            $papel->syncPermissions($permissoes);

            activity('papeis')->performedOn($papel)->causedBy($ator)
                ->withProperties(['permissoes' => $permissoes])
                ->log('Papel criado');

            return $papel;
        });
    }
}
