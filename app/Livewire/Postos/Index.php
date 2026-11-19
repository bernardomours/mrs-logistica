<?php

namespace App\Livewire\Postos;

use App\Livewire\Concerns\ListaCadastro;
use App\Models\Posto;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Postos')]
class Index extends Component
{
    use ListaCadastro;

    protected function modelo(): string
    {
        return Posto::class;
    }

    protected function consulta(): Builder
    {
        return Posto::query();
    }

    protected function rotulo(object $registro): string
    {
        return "Posto {$registro->nome}";
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'cidade', 'ativo'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'nome';
    }

    public function render(): View
    {
        return view('livewire.postos.index', [
            'postos' => $this->paginar($this->consulta(), ['nome', 'cidade'], ['cnpj']),
        ]);
    }
}
