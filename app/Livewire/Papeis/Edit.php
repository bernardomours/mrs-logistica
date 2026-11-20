<?php

namespace App\Livewire\Papeis;

use App\Support\CatalogoDePermissoes;
use App\Support\PapeisDoSistema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Editar papel')]
class Edit extends Create
{
    public function mount(?Role $papel = null): void
    {
        Gate::authorize('update', $papel);

        $this->form->preencher($papel);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->papel);

        $papel = $this->form->atualizar(auth()->user());

        $this->toastNaProximaPagina('Papel '.PapeisDoSistema::rotulo($papel->name).' atualizado.');

        $this->redirectRoute('papeis.index', navigate: true);
    }

    public function render(): View
    {
        return view($this->nomeDaView(), [
            'grupos' => CatalogoDePermissoes::grupos(),
            'pode' => fn (string $permissao) => auth()->user()->can($permissao),
            'ehPadrao' => PapeisDoSistema::ehDoSistema($this->form->papel->name),
        ]);
    }

    protected function nomeDaView(): string
    {
        return 'livewire.papeis.edit';
    }
}
