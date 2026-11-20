<?php

namespace App\Livewire\Usuarios;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\UsuarioForm;
use App\Models\User;
use App\Services\GestaoDeUsuarios;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Novo usuário')]
class Create extends Component
{
    use EmiteToast;

    public UsuarioForm $form;

    public function mount(): void
    {
        Gate::authorize('create', User::class);

        $filiais = app(GestaoDeUsuarios::class)->filiaisAtribuiveis(auth()->user());

        if ($filiais->count() === 1) {
            $this->form->filiais = [(string) $filiais->first()->id];
        }
    }

    public function salvar(): void
    {
        Gate::authorize('create', User::class);

        $usuario = $this->form->criar(auth()->user());

        $this->toastNaProximaPagina("Usuário {$usuario->name} cadastrado.");

        $this->redirectRoute('usuarios.index', navigate: true);
    }

    public function render(): View
    {
        $gestao = app(GestaoDeUsuarios::class);
        $papeis = $gestao->papeisAtribuiveis(auth()->user());
        $filiais = $gestao->filiaisAtribuiveis(auth()->user());

        return view($this->nomeDaView(), [
            'papeis' => $papeis,
            'filiais' => $filiais,
            'filiaisEscolhidas' => $filiais->whereIn('id', array_map('intval', $this->form->filiais)),
            'papelVeTodas' => (bool) $papeis->firstWhere('name', $this->form->papel)?->hasPermissionTo('filiais.ver_todas'),
            'editando' => $this->form->usuario !== null,
        ]);
    }

    protected function nomeDaView(): string
    {
        return 'livewire.usuarios.create';
    }
}
