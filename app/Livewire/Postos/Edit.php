<?php

namespace App\Livewire\Postos;

use App\Models\Posto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar posto')]
class Edit extends Create
{
    public function mount(?Posto $posto = null): void
    {
        Gate::authorize('update', $posto);

        $this->form->preencher($posto);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->posto);

        $posto = $this->form->atualizar();

        $this->toastNaProximaPagina("Posto {$posto->nome} atualizado.");

        $this->redirectRoute('postos.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.postos.edit');
    }
}
