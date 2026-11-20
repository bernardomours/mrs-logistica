<?php

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Services\GestaoDeUsuarios;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar usuário')]
class Edit extends Create
{
    public function mount(?User $usuario = null): void
    {
        $usuario = app(GestaoDeUsuarios::class)->consultaVisivelPara(auth()->user())->findOrFail($usuario?->getKey());

        Gate::authorize('update', $usuario);

        $this->form->preencher($usuario);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->usuario);

        $usuario = $this->form->atualizar(auth()->user());

        $this->toastNaProximaPagina("Usuário {$usuario->name} atualizado.");

        // Se a pessoa mudou o próprio papel, as permissões dela mudaram: reavalia antes de decidir para onde ir.
        auth()->user()->unsetRelation('roles')->unsetRelation('permissions');

        auth()->user()->can('usuarios.ver')
            ? $this->redirectRoute('usuarios.index', navigate: true)
            : $this->redirectRoute('dashboard', navigate: true);
    }

    protected function nomeDaView(): string
    {
        return 'livewire.usuarios.edit';
    }
}
