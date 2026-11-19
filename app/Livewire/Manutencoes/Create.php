<?php

namespace App\Livewire\Manutencoes;

use App\Enums\ManutencaoTipo;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\ManutencaoForm;
use App\Models\Abastecimento;
use App\Models\Manutencao;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\Numero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Nova manutenção')]
class Create extends Component
{
    use EmiteToast;

    public ManutencaoForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Manutencao::class);

        $this->form->data_manutencao = now()->toDateString();

        // Vindo da ficha do veículo (`?veiculo=ID`): já escolhe o veículo.
        $veiculo = request()->integer('veiculo');

        if ($veiculo > 0 && Veiculo::query()->ativos()->whereKey($veiculo)->exists()) {
            $this->form->veiculo_id = (string) $veiculo;
        }
    }

    /** O motorista (opcional) precisa ser da filial do veículo (RN-19). */
    public function updatedFormVeiculoId(): void
    {
        $veiculo = Veiculo::query()->find((int) $this->form->veiculo_id);

        if ($veiculo === null || ! Motorista::query()->whereKey((int) $this->form->motorista_id)->where('filial_id', $veiculo->filial_id)->exists()) {
            $this->form->motorista_id = '';
        }
    }

    public function salvar(): void
    {
        Gate::authorize('create', Manutencao::class);

        $this->form->criar();

        $this->toastNaProximaPagina('Manutenção registrada.');

        $this->redirectRoute('manutencoes.index', navigate: true);
    }

    /** RN-20: hodômetro abaixo da última leitura de abastecimento do veículo é aviso, não bloqueio. */
    public function avisoHodometro(): ?string
    {
        $odometro = Numero::paraDecimal($this->form->odometro_km, 1);

        if ($odometro === null || $this->form->veiculo_id === '') {
            return null;
        }

        $ultimo = Abastecimento::query()->where('veiculo_id', (int) $this->form->veiculo_id)->max('odometro_km');

        return $ultimo !== null && Dinheiro::comparar($odometro, (string) $ultimo, 1) < 0
            ? 'O hodômetro informado é menor que o último abastecimento deste veículo ('.Numero::formatar($ultimo, 1).' km). Confira.'
            : null;
    }

    /** @return array<string, mixed> */
    protected function opcoes(): array
    {
        $atual = $this->form->manutencao;
        $veiculo = $this->form->veiculo_id !== '' ? Veiculo::query()->find((int) $this->form->veiculo_id) : null;

        return [
            'veiculos' => Veiculo::query()->with('filial:id,codigo')
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhere('id', $atual->veiculo_id)))
                ->orderBy('placa')->get(['id', 'placa', 'filial_id']),
            'motoristas' => $veiculo === null ? collect() : Motorista::query()
                ->where('filial_id', $veiculo->filial_id)
                ->where(fn ($q) => $q->where('ativo', true)->when($atual?->motorista_id, fn ($c) => $c->orWhere('id', $atual->motorista_id)))
                ->orderBy('nome')->get(['id', 'nome']),
            'tipos' => ManutencaoTipo::cases(),
            'total' => $this->form->totalPrevisto(),
            'aviso' => $this->avisoHodometro(),
        ];
    }

    public function render(): View
    {
        return view('livewire.manutencoes.create', $this->opcoes());
    }
}
