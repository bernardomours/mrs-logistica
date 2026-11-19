<?php

namespace App\Livewire\Tarifas;

use App\Models\TarifaRota;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar tarifa de rota')]
class Edit extends Create
{
    public function mount(?TarifaRota $tarifa = null): void
    {
        Gate::authorize('update', $tarifa);

        $this->form->preencher($tarifa);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->tarifa);

        $this->form->atualizar();

        $this->toastNaProximaPagina('Tarifa atualizada.');

        $this->redirectRoute('tarifas.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.tarifas.edit', $this->opcoes());
    }
}
