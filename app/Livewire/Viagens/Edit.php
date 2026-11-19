<?php

namespace App\Livewire\Viagens;

use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar viagem')]
class Edit extends Create
{
    public function mount(?Viagem $viagem = null): void
    {
        Gate::authorize('update', $viagem);

        $this->form->preencher($viagem);
        $this->sugerirFrete();
        $this->valorAutomatico = $this->form->valor_frete;
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->viagem);

        $this->form->atualizar();

        $this->toastNaProximaPagina('Viagem atualizada.');

        $this->redirectRoute('viagens.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.viagens.edit', $this->opcoes());
    }
}
