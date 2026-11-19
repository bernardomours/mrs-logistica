<?php

namespace App\Livewire\Postos;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\PostoForm;
use App\Models\Posto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Novo posto')]
class Create extends Component
{
    use EmiteToast;

    public PostoForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Posto::class);
    }

    public function salvar(): void
    {
        Gate::authorize('create', Posto::class);

        $posto = $this->form->criar();

        $this->toastNaProximaPagina("Posto {$posto->nome} cadastrado.");

        $this->redirectRoute('postos.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.postos.create');
    }
}
