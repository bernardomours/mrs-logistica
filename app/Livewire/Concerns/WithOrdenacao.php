<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Ordenação por coluna. O componente declara `colunasOrdenaveis()` (lista branca).
 */
trait WithOrdenacao
{
    #[Url(as: 'ordem', history: true)]
    public string $ordenarPor = '';

    #[Url(as: 'dir', history: true)]
    public string $direcao = 'asc';

    /** @return array<int, string> */
    abstract protected function colunasOrdenaveis(): array;

    abstract protected function ordenacaoPadrao(): string;

    public function ordenar(string $coluna): void
    {
        if (! in_array($coluna, $this->colunasOrdenaveis(), true)) {
            return;
        }

        if ($this->colunaAtual() === $coluna) {
            $this->direcao = $this->direcao === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenarPor = $coluna;
            $this->direcao = 'asc';
        }

        $this->resetPage();
    }

    protected function colunaAtual(): string
    {
        return in_array($this->ordenarPor, $this->colunasOrdenaveis(), true)
            ? $this->ordenarPor
            : $this->ordenacaoPadrao();
    }

    protected function direcaoAtual(): string
    {
        return $this->direcao === 'desc' ? 'desc' : 'asc';
    }
}
