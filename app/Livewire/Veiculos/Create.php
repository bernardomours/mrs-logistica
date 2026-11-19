<?php

namespace App\Livewire\Veiculos;

use App\Enums\VeiculoTipo;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\VeiculoForm;
use App\Models\Filial;
use App\Models\Motorista;
use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Novo veículo')]
class Create extends Component
{
    use EmiteToast;

    public VeiculoForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Veiculo::class);

        $this->form->sugerirFilial();
    }

    /** O motorista padrão precisa ser da filial escolhida (RN-16/RN-19). */
    public function updatedFormFilialId(): void
    {
        $this->form->motorista_padrao_id = '';
    }

    public function salvar(): void
    {
        Gate::authorize('create', Veiculo::class);

        $veiculo = $this->form->criar();

        $this->toastNaProximaPagina("Veículo {$veiculo->placa_formatada} cadastrado.");

        $this->redirectRoute('veiculos.index', navigate: true);
    }

    /** @return array<string, mixed> */
    protected function opcoes(): array
    {
        return [
            'filiais' => Filial::query()->ativas()->acessiveis()->orderBy('nome')->get(['id', 'nome', 'codigo']),
            'motoristas' => $this->form->filial_id === '' ? collect() : Motorista::query()
                ->ativos()->where('filial_id', (int) $this->form->filial_id)->orderBy('nome')->get(['id', 'nome']),
            'tipos' => VeiculoTipo::cases(),
        ];
    }

    public function render(): View
    {
        return view('livewire.veiculos.create', $this->opcoes());
    }
}
