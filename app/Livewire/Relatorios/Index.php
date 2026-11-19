<?php

namespace App\Livewire\Relatorios;

use App\Services\Relatorios\Catalogo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Lista dos relatórios que o usuário pode abrir. */
#[Layout('components.layouts.app')]
#[Title('Relatórios')]
class Index extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->can('relatorios.ver'), 403);
    }

    public function render(): View
    {
        return view('livewire.relatorios.index', [
            'relatorios' => app(Catalogo::class)->visiveisPara(Auth::user()),
        ]);
    }
}
