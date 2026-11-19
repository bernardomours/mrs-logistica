<?php

namespace App\Livewire\Concerns;

/**
 * Avisos rápidos (toasts) exibidos pelo layout. Tipos: sucesso, erro, aviso.
 */
trait EmiteToast
{
    /** Toast na página atual. */
    protected function toast(string $mensagem, string $tipo = 'sucesso'): void
    {
        $this->dispatch('toast', mensagem: $mensagem, tipo: $tipo);
    }

    /** Toast que aparece na próxima página (use antes de redirecionar). */
    protected function toastNaProximaPagina(string $mensagem, string $tipo = 'sucesso'): void
    {
        session()->flash('toast', ['mensagem' => $mensagem, 'tipo' => $tipo]);
    }
}
