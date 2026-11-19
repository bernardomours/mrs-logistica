<?php

namespace App\Policies;

use App\Models\Filial;
use App\Models\User;
use App\Services\FilialAtual;

class FilialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('filiais.ver');
    }

    public function view(User $user, Filial $filial): bool
    {
        return $user->can('filiais.ver') && $this->acessa($filial);
    }

    public function create(User $user): bool
    {
        return $user->can('filiais.criar');
    }

    public function update(User $user, Filial $filial): bool
    {
        return $user->can('filiais.editar') && $this->acessa($filial);
    }

    /** Filiais nunca são excluídas, apenas desativadas (RN-17). */
    public function desativar(User $user, Filial $filial): bool
    {
        return $user->can('filiais.desativar') && $this->acessa($filial);
    }

    public function delete(User $user, Filial $filial): bool
    {
        return false;
    }

    private function acessa(Filial $filial): bool
    {
        $permitidas = app(FilialAtual::class)->permitidas();

        return $permitidas === null || in_array($filial->getKey(), $permitidas, true);
    }
}
