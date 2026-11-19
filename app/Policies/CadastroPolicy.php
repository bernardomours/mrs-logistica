<?php

namespace App\Policies;

use App\Models\User;
use App\Services\FilialAtual;
use Illuminate\Database\Eloquent\Model;

/**
 * Base das policies de cadastro. Permissões `recurso.acao` (CLAUDE.md §7); a subclasse informa o recurso.
 * Cadastros não são excluídos (RN-17): `delete` é falso, a menos que a subclasse permita.
 */
abstract class CadastroPolicy
{
    /** Prefixo das permissões, ex.: "motoristas". */
    abstract protected function recurso(): string;

    /** Se o model pertence a uma filial, só quem acessa a filial pode agir (RN-18). */
    protected function acessa(Model $modelo): bool
    {
        if (! array_key_exists('filial_id', $modelo->getAttributes())) {
            return true;
        }

        $permitidas = app(FilialAtual::class)->permitidas();

        return $permitidas === null || in_array((int) $modelo->getAttribute('filial_id'), $permitidas, true);
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->recurso().'.ver');
    }

    public function view(User $user, Model $modelo): bool
    {
        return $user->can($this->recurso().'.ver') && $this->acessa($modelo);
    }

    public function create(User $user): bool
    {
        return $user->can($this->recurso().'.criar');
    }

    public function update(User $user, Model $modelo): bool
    {
        return $user->can($this->recurso().'.editar') && $this->acessa($modelo);
    }

    /** Ativar/desativar usa a permissão de edição. */
    public function desativar(User $user, Model $modelo): bool
    {
        return $this->update($user, $modelo);
    }

    public function delete(User $user, Model $modelo): bool
    {
        return false;
    }
}
