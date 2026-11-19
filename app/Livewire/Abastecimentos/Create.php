<?php

namespace App\Livewire\Abastecimentos;

use App\Actions\Abastecimentos\RecalcularCadeia;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\AbastecimentoForm;
use App\Models\Abastecimento;
use App\Models\Motorista;
use App\Models\Posto;
use App\Models\Veiculo;
use App\Support\Dinheiro;
use App\Support\Numero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Novo abastecimento')]
class Create extends Component
{
    use EmiteToast;

    public AbastecimentoForm $form;

    public function mount(): void
    {
        Gate::authorize('create', Abastecimento::class);

        $this->form->data_abastecimento = now()->toDateString();
    }

    /** Ao escolher o veículo: motorista da mesma filial (preferindo o motorista padrão) — RN-16/RN-19. */
    public function updatedFormVeiculoId(): void
    {
        $this->form->km_anterior = '';

        $veiculo = Veiculo::query()->find((int) $this->form->veiculo_id);

        if ($veiculo === null) {
            return;
        }

        $motoristaServe = $this->form->motorista_id !== ''
            && Motorista::query()->whereKey((int) $this->form->motorista_id)->where('filial_id', $veiculo->filial_id)->exists();

        if (! $motoristaServe) {
            $padrao = $veiculo->motorista_padrao_id;
            $this->form->motorista_id = $padrao !== null && Motorista::query()->ativos()->whereKey($padrao)->exists() ? (string) $padrao : '';
        }
    }

    public function updatedFormLitros(): void
    {
        $this->form->atualizarTripe('litros');
    }

    public function updatedFormPrecoLitro(): void
    {
        $this->form->atualizarTripe('preco_litro');
    }

    public function updatedFormValorTotal(): void
    {
        $this->form->atualizarTripe('valor_total');
    }

    public function salvar(): void
    {
        Gate::authorize('create', Abastecimento::class);

        $abastecimento = $this->form->criar();

        $this->toastNaProximaPagina(
            'Abastecimento lançado'.($abastecimento->media_km_l ? ' — média de '.Numero::formatar($abastecimento->media_km_l).' km/l.' : '.')
        );

        $this->redirectRoute('abastecimentos.index', navigate: true);
    }

    /**
     * Pré-visualização do que a cadeia de hodômetro vai gravar (RN-07/RN-08): só exibição.
     *
     * @return array{referencia: ?string, precisa_km_inicial: bool, rodados: ?string, media: ?string}
     */
    public function previa(): array
    {
        $veiculo = $this->form->veiculo_id !== '' ? Veiculo::query()->find((int) $this->form->veiculo_id) : null;
        $odometro = Numero::paraDecimal($this->form->odometro_km, 1);

        $resultado = ['referencia' => null, 'precisa_km_inicial' => false, 'rodados' => null, 'media' => null];

        if ($veiculo === null) {
            return $resultado;
        }

        // Ao escolher o veículo (ainda sem o km final), a referência é a última leitura dele; com o km final, a anterior a ele.
        $automatica = app(RecalcularCadeia::class)->referenciaPara($veiculo, $odometro ?? '9999999.9', $this->form->abastecimento?->id);
        $resultado['precisa_km_inicial'] = $automatica === null;
        $resultado['referencia'] = $automatica ?? Numero::paraDecimal($this->form->km_anterior, 1);

        if ($odometro === null) {
            return $resultado;
        }

        if ($resultado['referencia'] !== null && Dinheiro::comparar($odometro, $resultado['referencia'], 1) >= 0) {
            $resultado['rodados'] = Dinheiro::subtrair($odometro, $resultado['referencia'], 1);
            $litros = Numero::paraDecimal($this->form->litros, 3);

            if ($litros !== null && Dinheiro::comparar($resultado['rodados'], '0', 1) > 0) {
                $resultado['media'] = Dinheiro::dividir($resultado['rodados'], $litros, 4);
            }
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    protected function opcoes(): array
    {
        $atual = $this->form->abastecimento;
        $veiculo = $this->form->veiculo_id !== '' ? Veiculo::query()->find((int) $this->form->veiculo_id) : null;

        return [
            'veiculos' => Veiculo::query()->with('filial:id,codigo')
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhere('id', $atual->veiculo_id)))
                ->orderBy('placa')->get(['id', 'placa', 'filial_id']),
            'motoristas' => $veiculo === null ? collect() : Motorista::query()
                ->where('filial_id', $veiculo->filial_id)
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhere('id', $atual->motorista_id)))
                ->orderBy('nome')->get(['id', 'nome']),
            'postos' => Posto::query()
                ->where(fn ($q) => $q->where('ativo', true)->when($atual?->posto_id, fn ($c) => $c->orWhere('id', $atual->posto_id)))
                ->orderBy('nome')->get(['id', 'nome']),
            'combustiveis' => config('frota.combustiveis'),
            'previa' => $this->previa(),
        ];
    }

    public function render(): View
    {
        return view('livewire.abastecimentos.create', $this->opcoes());
    }
}
