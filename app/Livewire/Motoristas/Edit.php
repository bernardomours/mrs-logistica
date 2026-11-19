<?php

namespace App\Livewire\Motoristas;

use App\Models\Filial;
use App\Models\Motorista;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar motorista')]
class Edit extends Create
{
    public function mount(?Motorista $motorista = null): void
    {
        Gate::authorize('update', $motorista);

        $this->form->preencher($motorista);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->motorista);

        $motorista = $this->form->atualizar();

        $this->toastNaProximaPagina("Motorista {$motorista->nome} atualizado.");

        $this->redirectRoute('motoristas.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.motoristas.edit', [
            'filiais' => Filial::query()->ativas()->acessiveis()->orderBy('nome')->get(['id', 'nome', 'codigo']),
        ]);
    }
}
