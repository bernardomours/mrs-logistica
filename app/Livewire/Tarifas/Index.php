<?php

namespace App\Livewire\Tarifas;

use App\Actions\Tarifas\ExcluirTarifa;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\Local;
use App\Models\TarifaRota;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tarifas de rota')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    /** Situação da vigência: todas | vigentes | encerradas. Substitui o filtro ativo/inativo. */
    #[Url(as: 'vigencia', history: true)]
    public string $vigencia = 'todas';

    public function mount(): void
    {
        Gate::authorize('viewAny', TarifaRota::class);
    }

    protected function filtrosPadrao(): array
    {
        return ['busca' => '', 'vigencia' => 'todas'];
    }

    protected function colunasOrdenaveis(): array
    {
        return ['valor_frete', 'vigencia_inicio', 'vigencia_fim'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'vigencia_inicio';
    }

    public function updatedVigencia(): void
    {
        $this->resetPage();
    }

    public function excluir(int $id): void
    {
        $tarifa = TarifaRota::query()->findOrFail($id);

        Gate::authorize('delete', $tarifa);

        app(ExcluirTarifa::class)->handle($tarifa);

        $this->toast('Tarifa excluída.');
    }

    public function render(): View
    {
        $hoje = now()->toDateString();

        $tarifas = TarifaRota::query()
            ->with(['origem:id,nome', 'destino:id,nome'])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%';

                $q->where(function (Builder $busca) use ($termo) {
                    $busca->whereIn('origem_local_id', Local::query()->where('nome', 'like', $termo)->select('id'))
                        ->orWhereIn('destino_local_id', Local::query()->where('nome', 'like', $termo)->select('id'));
                });
            })
            ->when($this->vigencia === 'vigentes', fn (Builder $q) => $q
                ->whereDate('vigencia_inicio', '<=', $hoje)
                ->where(fn ($v) => $v->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>=', $hoje)))
            ->when($this->vigencia === 'encerradas', fn (Builder $q) => $q
                ->where(fn ($v) => $v->whereDate('vigencia_inicio', '>', $hoje)->orWhereDate('vigencia_fim', '<', $hoje)))
            ->orderBy($this->colunaAtual(), $this->ordenarPor === '' ? 'desc' : $this->direcaoAtual())
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.tarifas.index', ['tarifas' => $tarifas]);
    }
}
