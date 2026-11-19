<?php

namespace App\Livewire\Abastecimentos;

use App\Actions\Abastecimentos\ExcluirAbastecimento;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Posto;
use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Abastecimentos')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    #[Url(history: true)]
    public string $veiculo = '';

    #[Url(history: true)]
    public string $motorista = '';

    #[Url(history: true)]
    public string $posto = '';

    #[Url(history: true)]
    public string $de = '';

    #[Url(history: true)]
    public string $ate = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Abastecimento::class);
    }

    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'veiculo' => '', 'motorista' => '', 'posto' => '', 'de' => '', 'ate' => ''];
    }

    protected function colunasOrdenaveis(): array
    {
        return ['data_abastecimento', 'odometro_km', 'litros', 'valor_total', 'media_km_l'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'data_abastecimento';
    }

    public function updatedVeiculo(): void
    {
        $this->resetPage();
    }

    public function updatedMotorista(): void
    {
        $this->resetPage();
    }

    public function updatedPosto(): void
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
        $abastecimento = Abastecimento::query()->findOrFail($id);

        Gate::authorize('delete', $abastecimento);

        app(ExcluirAbastecimento::class)->handle($abastecimento, Auth::user());

        $this->toast('Abastecimento excluído.');
    }

    private function consulta(): Builder
    {
        return Abastecimento::query()
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';

                $q->where(fn (Builder $b) => $b
                    ->whereHas('veiculo', fn ($v) => $v->where('placa', 'like', $termo))
                    ->orWhereHas('posto', fn ($p) => $p->where('nome', 'like', $termo))
                    ->orWhere('nota_fiscal', 'like', $termo));
            })
            ->when($this->veiculo !== '', fn (Builder $q) => $q->where('veiculo_id', (int) $this->veiculo))
            ->when($this->motorista !== '', fn (Builder $q) => $q->where('motorista_id', (int) $this->motorista))
            ->when($this->posto !== '', fn (Builder $q) => $q->where('posto_id', (int) $this->posto))
            ->when($this->de !== '', fn (Builder $q) => $q->whereDate('data_abastecimento', '>=', $this->de))
            ->when($this->ate !== '', fn (Builder $q) => $q->whereDate('data_abastecimento', '<=', $this->ate));
    }

    public function render(): View
    {
        $consulta = $this->consulta();

        $totais = (clone $consulta)
            ->selectRaw('count(*) as quantidade, coalesce(sum(litros), 0) as litros, coalesce(sum(valor_total), 0) as valor')
            ->toBase()->first();

        $abastecimentos = $consulta
            ->with(['veiculo:id,placa,capacidade_tanque_l', 'motorista:id,nome', 'posto:id,nome'])
            ->orderBy($this->colunaAtual(), $this->ordenarPor === '' ? 'desc' : $this->direcaoAtual())
            ->orderByDesc('odometro_km')
            ->paginate(15);

        return view('livewire.abastecimentos.index', [
            'abastecimentos' => $abastecimentos,
            'totais' => $totais,
            'veiculos' => Veiculo::query()->orderBy('placa')->get(['id', 'placa']),
            'motoristas' => Motorista::query()->orderBy('nome')->get(['id', 'nome']),
            'postos' => Posto::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
}
