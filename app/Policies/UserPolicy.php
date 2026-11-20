<?php

namespace App\Policies;

use App\Models\User;
use App\Services\GestaoDeUsuarios;
use App\Support\PapeisDoSistema;

/** Usuários da equipe. Regras de "quem gerencia quem" ficam em {@see GestaoDeUsuarios}. */
class UserPolicy
{
    public function __construct(private readonly GestaoDeUsuarios $gestao) {}

    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.ver');
    }

    public function view(User $user, User $alvo): bool
    {
        return $user->can('usuarios.ver') && ! $this->gestao->ehUsuarioDeMotorista($alvo);
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.criar');
    }

    public function update(User $user, User $alvo): bool
    {
        return $user->can('usuarios.editar') && $this->gestao->podeGerenciar($user, $alvo);
    }

    public function desativar(User $user, User $alvo): bool
    {
        return $user->can('usuarios.desativar') && $this->podeAgirSobreSi($user, $alvo) && $this->gestao->podeGerenciar($user, $alvo);
    }

    public function delete(User $user, User $alvo): bool
    {
        return $user->can('usuarios.excluir') && $this->podeAgirSobreSi($user, $alvo) && $this->gestao->podeGerenciar($user, $alvo);
    }

    /** Só o administrador desativa, exclui ou rebaixa o próprio usuário (o último administrador ativo continua protegido). */
    private function podeAgirSobreSi(User $user, User $alvo): bool
    {
        return ! $user->is($alvo) || $user->hasRole(PapeisDoSistema::ADMIN);
    }
}
