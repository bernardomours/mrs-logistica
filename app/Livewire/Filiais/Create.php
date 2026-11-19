<?php

namespace App\Livewire\Filiais;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\FilialForm;
use App\Models\Filial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Nova filial')]
class Create extends Component
{
    use EmiteToast;

    public FilialForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Filial::class);
    }

    public function salvar(): void
    {
        Gate::authorize('create', Filial::class);

        $filial = $this->form->criar();

        $this->toastNaProximaPagina("Filial {$filial->nome} cadastrada.");

        $this->redirectRoute('filiais.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.filiais.create');
    }
}
