<?php

namespace App\Livewire\Tarifas;

use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\TarifaForm;
use App\Models\Local;
use App\Models\TarifaRota;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Nova tarifa de rota')]
class Create extends Component
{
    use EmiteToast;

    public TarifaForm $form;

    public function mount(): void
    {
        Gate::authorize('create', TarifaRota::class);

        $this->form->vigencia_inicio = now()->toDateString();
    }

    /** Seleciona automaticamente o local recém-cadastrado pelo modal "Novo local". */
    #[On('local-criado')]
    public function selecionarLocalCriado(int $id, ?string $alvo = null): void
    {
        if ($alvo === 'origem') {
            $this->form->origem_local_id = (string) $id;
        } elseif ($alvo === 'destino') {
            $this->form->destino_local_id = (string) $id;
        }
    }

    public function salvar(): void
    {
        Gate::authorize('create', TarifaRota::class);

        $this->form->criar();

        $this->toastNaProximaPagina('Tarifa cadastrada.');

        $this->redirectRoute('tarifas.index', navigate: true);
    }

    /** @return array<string, mixed> */
    protected function opcoes(): array
    {
        return [
            'locais' => Local::query()->ativos()->orderBy('nome')->get(['id', 'nome']),
        ];
    }

    public function render(): View
    {
        return view('livewire.tarifas.create', $this->opcoes());
    }
}
