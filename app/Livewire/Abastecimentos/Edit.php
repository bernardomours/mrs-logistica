<?php

namespace App\Livewire\Abastecimentos;

use App\Models\Abastecimento;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar abastecimento')]
class Edit extends Create
{
    public function mount(?Abastecimento $abastecimento = null): void
    {
        Gate::authorize('update', $abastecimento);

        $this->form->preencher($abastecimento);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->abastecimento);

        $this->form->atualizar();

        $this->toastNaProximaPagina('Abastecimento atualizado.');

        $this->redirectRoute('abastecimentos.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.abastecimentos.edit', $this->opcoes());
    }
}
