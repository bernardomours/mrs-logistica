<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Busca textual + filtro de status (todos/ativos/inativos) na query string, com paginação.
 * O componente define `filtrosPadrao()` se quiser filtros extras para o "Limpar filtros".
 */
trait WithFiltros
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $busca = '';

    #[Url(history: true)]
    public string $status = 'todos';

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'status' => 'todos'];
    }

    public function limparFiltros(): void
    {
        foreach ($this->filtrosPadrao() as $propriedade => $valor) {
            $this->{$propriedade} = $valor;
        }

        $this->resetPage();
    }

    public function getTemFiltrosProperty(): bool
    {
        foreach ($this->filtrosPadrao() as $propriedade => $valor) {
            if ($this->{$propriedade} !== $valor) {
                return true;
            }
        }

        return false;
    }
}
