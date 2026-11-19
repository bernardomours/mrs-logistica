<?php

namespace App\Livewire\Viagens;

use App\Actions\Viagens\CancelarViagem;
use App\Actions\Viagens\ExcluirViagem;
use App\Enums\ViagemStatus;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Viagens')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    #[Url(history: true)]
    public string $motorista = '';

    #[Url(history: true)]
    public string $veiculo = '';

    #[Url(history: true)]
    public string $de = '';

    #[Url(history: true)]
    public string $ate = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Viagem::class);
    }

    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'status' => 'todos', 'motorista' => '', 'veiculo' => '', 'de' => '', 'ate' => ''];
    }

    protected function colunasOrdenaveis(): array
    {
        return ['data_viagem', 'valor_frete', 'status'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'data_viagem';
    }

    public function updatedMotorista(): void
    {
        $this->resetPage();
    }

    public function updatedVeiculo(): void
    {
        $this->resetPage();
    }

    public function updatedDe(): void
    {
        $this->resetPage();
    }

    public function updatedAte(): void
    {
        $this->resetPage();
    }

    public function cancelar(int $id): void
    {
        $viagem = Viagem::query()->findOrFail($id);

        Gate::authorize('cancelar', $viagem);

        app(CancelarViagem::class)->handle($viagem, Auth::user());

        $this->toast('Viagem cancelada.');
    }

    public function excluir(int $id): void
    {
        $viagem = Viagem::query()->findOrFail($id);

        Gate::authorize('delete', $viagem);

        app(ExcluirViagem::class)->handle($viagem, Auth::user());

        $this->toast('Viagem excluída.');
    }

    private function consulta(): Builder
    {
        return Viagem::query()
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';

                $q->where(fn (Builder $b) => $b
                    ->whereHas('origem', fn ($o) => $o->where('nome', 'like', $termo))
                    ->orWhereHas('destino', fn ($d) => $d->where('nome', 'like', $termo)));
            })
            ->when($this->status !== 'todos' && ViagemStatus::tryFrom($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when($this->motorista !== '', fn (Builder $q) => $q->where('motorista_id', (int) $this->motorista))
            ->when($this->veiculo !== '', fn (Builder $q) => $q->where('veiculo_id', (int) $this->veiculo))
            ->when($this->de !== '', fn (Builder $q) => $q->whereDate('data_viagem', '>=', $this->de))
            ->when($this->ate !== '', fn (Builder $q) => $q->whereDate('data_viagem', '<=', $this->ate));
    }

    public function render(): View
    {
        $consulta = $this->consulta();

        $totais = (clone $consulta)->concluidas()
            ->selectRaw('count(*) as quantidade, coalesce(sum(valor_frete), 0) as frete, coalesce(sum(comissao_valor), 0) as comissao')
            ->toBase()->first();

        $viagens = $consulta
            ->with(['motorista:id,nome', 'veiculo:id,placa', 'origem:id,nome', 'destino:id,nome', 'tarifa'])
            ->orderBy($this->colunaAtual(), $this->ordenarPor === '' ? 'desc' : $this->direcaoAtual())
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.viagens.index', [
            'viagens' => $viagens,
            'totais' => $totais,
            'motoristas' => Motorista::query()->orderBy('nome')->get(['id', 'nome']),
            'veiculos' => Veiculo::query()->orderBy('placa')->get(['id', 'placa']),
            'statusOpcoes' => ViagemStatus::cases(),
        ]);
    }
}
