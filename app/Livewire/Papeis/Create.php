<?php

namespace App\Livewire\Papeis;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\PapelForm;
use App\Support\CatalogoDePermissoes;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Novo papel')]
class Create extends Component
{
    use EmiteToast;

    public PapelForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Role::class);
    }

    /** Marca ou desmarca de uma vez as permissões de um grupo (só as que o usuário pode conceder). */
    public function marcarGrupo(string $grupo, bool $marcar): void
    {
        $permissoes = array_keys(CatalogoDePermissoes::grupos()[$grupo]['permissoes'] ?? []);
        $permissoes = array_values(array_filter($permissoes, fn (string $permissao) => auth()->user()->can($permissao)));

        $this->form->permissoes = array_values($marcar
            ? array_unique(array_merge($this->form->permissoes, $permissoes))
            : array_diff($this->form->permissoes, $permissoes));
    }

    public function salvar(): void
    {
        Gate::authorize('create', Role::class);

        $papel = $this->form->criar(auth()->user());

        $this->toastNaProximaPagina("Papel {$papel->name} criado.");

        $this->redirectRoute('papeis.index', navigate: true);
    }

    public function render(): View
    {
        return view($this->nomeDaView(), [
            'grupos' => CatalogoDePermissoes::grupos(),
            'pode' => fn (string $permissao) => auth()->user()->can($permissao),
            'ehPadrao' => false,
        ]);
    }

    protected function nomeDaView(): string
    {
        return 'livewire.papeis.create';
    }
}
