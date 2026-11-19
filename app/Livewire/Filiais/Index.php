<?php

namespace App\Livewire\Filiais;

use App\Actions\Filiais\AlternarAtivoFilial;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Concerns\WithFiltros;
use App\Livewire\Concerns\WithOrdenacao;
use App\Models\Filial;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Filiais')]
class Index extends Component
{
    use EmiteToast, WithFiltros, WithOrdenacao;

    public function mount(): void
    {
        Gate::authorize('viewAny', Filial::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'codigo', 'cidade', 'ativo'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'nome';
    }

    public function alternarAtivo(int $id): void
    {
        $filial = Filial::query()->acessiveis()->findOrFail($id);

        Gate::authorize('desativar', $filial);

        try {
            $filial = app(AlternarAtivoFilial::class)->handle($filial);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast($filial->ativo ? "Filial {$filial->nome} ativada." : "Filial {$filial->nome} desativada.");
    }

    public function render(): View
    {
        $filiais = Filial::query()
            ->acessiveis()
            ->when($this->busca !== '', function ($query) {
                $termo = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->busca)).'%';

                $query->where(fn ($q) => $q
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo)
                    ->orWhere('cidade', 'like', $termo));
            })
            ->when($this->status === 'ativos', fn ($q) => $q->where('ativo', true))
            ->when($this->status === 'inativos', fn ($q) => $q->where('ativo', false))
            ->orderBy($this->colunaAtual(), $this->direcaoAtual())
            ->paginate(15);

        return view('livewire.filiais.index', ['filiais' => $filiais]);
    }
}
