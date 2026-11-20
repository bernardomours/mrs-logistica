<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PapeisDoSistema;
use Spatie\Permission\Models\Role;

/** Papéis e permissões. Administrador e motorista são protegidos; os papéis padrão não são excluídos. */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('papeis.gerenciar');
    }

    public function view(User $user, Role $papel): bool
    {
        return $user->can('papeis.gerenciar');
    }

    public function create(User $user): bool
    {
        return $user->can('papeis.gerenciar');
    }

    public function update(User $user, Role $papel): bool
    {
        return $user->can('papeis.gerenciar') && ! PapeisDoSistema::travado($papel->name);
    }

    public function delete(User $user, Role $papel): bool
    {
        return $user->can('papeis.gerenciar') && ! PapeisDoSistema::ehDoSistema($papel->name);
    }
}
