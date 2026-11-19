<?php

namespace App\Livewire\Layout;

use App\Services\FilialAtual;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Seletor de filial ativa (RN-18), exibido no topo do menu lateral. */
class SeletorDeFilial extends Component
{
    public string $selecionada = 'todas';

    public function mount(FilialAtual $filiais): void
    {
        $this->selecionada = (string) ($filiais->selecionada() ?? 'todas');
    }

    public function updatedSelecionada(string $valor, FilialAtual $filiais): void
    {
        $filiais->selecionar($valor === 'todas' ? null : (int) $valor);

        // Recarrega a página para todos os componentes respeitarem a nova filial.
        $this->js('window.location.reload()');
    }

    public function render(FilialAtual $filiais): View
    {
        $disponiveis = $filiais->disponiveis();
        $permitidas = $filiais->permitidas();
        $selecionada = $filiais->selecionada();

        return view('livewire.layout.seletor-de-filial', [
            'disponiveis' => $disponiveis,
            'podeVerTodas' => $permitidas === null || $disponiveis->count() > 1,
            'atual' => $selecionada !== null ? $disponiveis->firstWhere('id', $selecionada) : null,
        ]);
    }
}
