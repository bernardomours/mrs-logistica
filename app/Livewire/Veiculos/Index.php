<?php

namespace App\Livewire\Veiculos;

use App\Livewire\Concerns\ListaCadastro;
use App\Models\Veiculo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Veículos')]
class Index extends Component
{
    use ListaCadastro;

    protected function modelo(): string
    {
        return Veiculo::class;
    }

    protected function consulta(): Builder
    {
        return Veiculo::query()->with(['filial:id,nome,codigo', 'motoristaPadrao:id,nome']);
    }

    protected function rotulo(object $registro): string
    {
        return "Veículo {$registro->placa_formatada}";
    }

    protected function colunasOrdenaveis(): array
    {
        return ['placa', 'tipo', 'marca', 'ativo'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'placa';
    }

    public function render(): View
    {
        return view('livewire.veiculos.index', [
            'veiculos' => $this->paginar($this->consulta(), ['placa', 'marca', 'modelo']),
        ]);
    }
}
