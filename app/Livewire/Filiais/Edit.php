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
#[Title('Editar filial')]
class Edit extends Component
{
    use EmiteToast;

    public FilialForm $form;

    public function mount(Filial $filial): void
    {
        Gate::authorize('update', $filial);

        $this->form->preencher($filial);
    }

    public function salvar(): void
    {
        Gate::authorize('update', $this->form->filial);

        $filial = $this->form->atualizar();

        $this->toastNaProximaPagina("Filial {$filial->nome} atualizada.");

        $this->redirectRoute('filiais.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.filiais.edit');
    }
}
