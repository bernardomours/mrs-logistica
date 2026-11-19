<?php

namespace App\Livewire\Motoristas;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\MotoristaForm;
use App\Models\Filial;
use App\Models\Motorista;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Novo motorista')]
class Create extends Component
{
    use EmiteToast;

    public MotoristaForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Motorista::class);

        $this->form->sugerirFilial();
    }

    public function salvar(): void
    {
        Gate::authorize('create', Motorista::class);

        $motorista = $this->form->criar();

        $this->toastNaProximaPagina("Motorista {$motorista->nome} cadastrado.");

        $this->redirectRoute('motoristas.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.motoristas.create', [
            'filiais' => Filial::query()->ativas()->acessiveis()->orderBy('nome')->get(['id', 'nome', 'codigo']),
        ]);
    }
}
