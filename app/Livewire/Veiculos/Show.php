<?php

namespace App\Livewire\Veiculos;

use App\Models\Abastecimento;
use App\Models\Manutencao;
use App\Models\Veiculo;
use App\Services\AlertasDePreventiva;
use App\Support\Dinheiro;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ficha do veículo: linha do tempo de abastecimentos e manutenções, hodômetro atual e alertas de preventiva. */
#[Layout('components.layouts.app')]
#[Title('Ficha do veículo')]
class Show extends Component
{
    private const LIMITE = 40;

    public int $veiculoId;

    public function mount(Veiculo $veiculo): void
    {
        Gate::authorize('view', $veiculo);

        $this->veiculoId = $veiculo->id;
    }

    public function render(): View
    {
        $veiculo = Veiculo::query()->with(['filial:id,nome,codigo', 'motoristaPadrao:id,nome'])->findOrFail($this->veiculoId);

        $abastecimentos = Abastecimento::query()->where('veiculo_id', $veiculo->id)
            ->with('motorista:id,nome')->orderByDesc('data_abastecimento')->orderByDesc('odometro_km')->limit(self::LIMITE)->get();
        $manutencoes = Manutencao::query()->where('veiculo_id', $veiculo->id)
            ->with('motorista:id,nome')->orderByDesc('data_manutencao')->orderByDesc('id')->limit(self::LIMITE)->get();

        /** @var Collection<int, array{data: CarbonInterface, tipo: string, item: mixed}> $linhaDoTempo */
        $linhaDoTempo = $abastecimentos->map(fn ($a) => ['data' => $a->data_abastecimento, 'tipo' => 'abastecimento', 'item' => $a])
            ->concat($manutencoes->map(fn ($m) => ['data' => $m->data_manutencao, 'tipo' => 'manutencao', 'item' => $m]))
            ->sortByDesc(fn ($e) => $e['data']->timestamp)->take(self::LIMITE)->values();

        $hodometro = collect([
            Abastecimento::query()->where('veiculo_id', $veiculo->id)->max('odometro_km'),
            Manutencao::query()->where('veiculo_id', $veiculo->id)->max('odometro_km'),
            $veiculo->odometro_inicial,
        ])->filter(fn ($v) => $v !== null)->map(fn ($v) => (string) $v)->sort(fn ($a, $b) => bccomp($b, $a, 1))->first();

        return view('livewire.veiculos.show', [
            'veiculo' => $veiculo,
            'linhaDoTempo' => $linhaDoTempo,
            'hodometro' => $hodometro,
            'alertas' => app(AlertasDePreventiva::class)->handle([$veiculo->id]),
            'totalManutencao' => Dinheiro::total(Manutencao::query()->where('veiculo_id', $veiculo->id)->pluck('valor_total')),
            'totalCombustivel' => Dinheiro::total(Abastecimento::query()->where('veiculo_id', $veiculo->id)->pluck('valor_total')),
            'limite' => self::LIMITE,
        ]);
    }
}
