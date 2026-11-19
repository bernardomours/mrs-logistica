<?php

namespace App\Livewire\Veiculos;

use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar veículo')]
class Edit extends Create
{
    public function mount(?Veiculo $veiculo = null): void
    {
        Gate::authorize('update', $veiculo);

        $this->form->preencher($veiculo);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->veiculo);

        $veiculo = $this->form->atualizar();

        $this->toastNaProximaPagina("Veículo {$veiculo->placa_formatada} atualizado.");

        $this->redirectRoute('veiculos.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.veiculos.edit', $this->opcoes());
    }
}
