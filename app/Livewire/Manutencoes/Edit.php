<?php

namespace App\Livewire\Manutencoes;

use App\Models\Manutencao;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.app')]
#[Title('Editar manutenção')]
class Edit extends Create
{
    public function mount(?Manutencao $manutencao = null): void
    {
        Gate::authorize('update', $manutencao);

        $this->form->preencher($manutencao);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->manutencao);

        $this->form->atualizar();

        $this->toastNaProximaPagina('Manutenção atualizada.');

        $this->redirectRoute('manutencoes.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.manutencoes.edit', $this->opcoes());
    }
}
