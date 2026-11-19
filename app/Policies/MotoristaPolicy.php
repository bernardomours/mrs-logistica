<?php

namespace App\Policies;

use App\Models\Motorista;
use App\Models\User;

class MotoristaPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'motoristas';
    }

    /** Criar o acesso ao portal (RN-21): motorista ativo, ainda sem acesso. */
    public function criarAcesso(User $user, Motorista $motorista): bool
    {
        return $user->can('usuarios.criar') && $this->acessa($motorista) && $motorista->ativo && $motorista->user_id === null;
    }

    public function redefinirSenha(User $user, Motorista $motorista): bool
    {
        return $user->can('usuarios.editar') && $this->acessa($motorista) && $motorista->user_id !== null;
    }
}
