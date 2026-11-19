<?php

namespace App\Livewire\Manutencoes;

use App\Actions\Manutencoes\ExcluirManutencao;
use App\Enums\ManutencaoTipo;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Services\AlertasDePreventiva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Manutenções')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    #[Url(history: true)]
    public string $veiculo = '';

    #[Url(history: true)]
    public string $de = '';

    #[Url(history: true)]
    public string $ate = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Manutencao::class);
    }

    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'status' => 'todos', 'veiculo' => '', 'de' => '', 'ate' => ''];
    }

    protected function colunasOrdenaveis(): array
    {
        return ['data_manutencao', 'valor_total', 'tipo'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'data_manutencao';
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

    public function excluir(int $id): void
    {
        $manutencao = Manutencao::query()->findOrFail($id);

        Gate::authorize('delete', $manutencao);

        app(ExcluirManutencao::class)->handle($manutencao, Auth::user());

        $this->toast('Manutenção excluída.');
    }

    private function consulta(): Builder
    {
        return Manutencao::query()
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';

                $q->where(fn (Builder $b) => $b
                    ->where('descricao', 'like', $termo)
                    ->orWhere('oficina', 'like', $termo)
                    ->orWhere('nota_fiscal', 'like', $termo)
                    ->orWhereHas('veiculo', fn ($v) => $v->where('placa', 'like', $termo)));
            })
            ->when($this->status !== 'todos' && ManutencaoTipo::tryFrom($this->status), fn (Builder $q) => $q->where('tipo', $this->status))
            ->when($this->veiculo !== '', fn (Builder $q) => $q->where('veiculo_id', (int) $this->veiculo))
            ->when($this->de !== '', fn (Builder $q) => $q->whereDate('data_manutencao', '>=', $this->de))
            ->when($this->ate !== '', fn (Builder $q) => $q->whereDate('data_manutencao', '<=', $this->ate));
    }

    public function render(): View
    {
        $consulta = $this->consulta();

        $totais = (clone $consulta)
            ->selectRaw('count(*) as quantidade, coalesce(sum(valor_pecas), 0) as pecas, coalesce(sum(valor_mao_obra), 0) as mao_de_obra, coalesce(sum(valor_total), 0) as total')
            ->toBase()->first();

        $manutencoes = $consulta
            ->with(['veiculo:id,placa', 'motorista:id,nome'])
            ->orderBy($this->colunaAtual(), $this->ordenarPor === '' ? 'desc' : $this->direcaoAtual())
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.manutencoes.index', [
            'manutencoes' => $manutencoes,
            'totais' => $totais,
            'veiculos' => Veiculo::query()->orderBy('placa')->get(['id', 'placa']),
            'tipos' => ManutencaoTipo::cases(),
            'alertas' => app(AlertasDePreventiva::class)->handle(),
        ]);
    }
}
