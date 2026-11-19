<?php

namespace App\Livewire\Viagens;

use App\Enums\ViagemStatus;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\ViagemForm;
use App\Models\Local;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use App\Services\SugestorDeFrete;
use App\Support\Dinheiro;
use App\Support\Numero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Nova viagem')]
class Create extends Component
{
    use EmiteToast;

    public ViagemForm $form;

    /** Valor da tarifa vigente para a rota/data escolhidas (formatado), para o aviso na tela. */
    public ?string $freteSugerido = null;

    /** Último valor preenchido automaticamente; só é trocado se o usuário ainda não digitou outro. */
    public ?string $valorAutomatico = null;

    public function mount(): void
    {
        Gate::authorize('create', Viagem::class);

        $this->form->data_viagem = now()->toDateString();
    }

    /** Ao escolher o motorista: veículo da mesma filial (preferindo o veículo padrão) e a porcentagem dele (RN-02/RN-16). */
    public function updatedFormMotoristaId(): void
    {
        $motorista = Motorista::query()->find((int) $this->form->motorista_id);

        if ($motorista === null) {
            return;
        }

        $veiculoServe = $this->form->veiculo_id !== ''
            && Veiculo::query()->whereKey((int) $this->form->veiculo_id)->where('filial_id', $motorista->filial_id)->exists();

        if (! $veiculoServe) {
            $padrao = Veiculo::query()->ativos()->where('filial_id', $motorista->filial_id)->where('motorista_padrao_id', $motorista->id)->value('id');
            $this->form->veiculo_id = $padrao !== null ? (string) $padrao : '';
        }

        if (auth()->user()->can('comissoes.ver')) {
            $this->form->comissao_percentual = Numero::paraCampo($motorista->comissao_percentual);
        }
    }

    public function updatedFormOrigemLocalId(): void
    {
        $this->sugerirFrete();
    }

    public function updatedFormDestinoLocalId(): void
    {
        $this->sugerirFrete();
    }

    public function updatedFormDataViagem(): void
    {
        $this->sugerirFrete();
    }

    /** RN-10: pré-preenche o frete com a tarifa vigente na data, sem sobrescrever um valor digitado à mão. */
    protected function sugerirFrete(): void
    {
        $f = $this->form;
        $tarifa = ($f->origem_local_id !== '' && $f->destino_local_id !== '' && $f->data_viagem !== '' && $f->origem_local_id !== $f->destino_local_id)
            ? app(SugestorDeFrete::class)->paraRota((int) $f->origem_local_id, (int) $f->destino_local_id, $f->data_viagem)
            : null;

        $this->freteSugerido = $tarifa ? Numero::formatar($tarifa->valor_frete) : null;

        if ($tarifa === null) {
            return;
        }

        $novo = Numero::paraCampo($tarifa->valor_frete);

        if ($f->valor_frete === '' || $f->valor_frete === $this->valorAutomatico) {
            $f->valor_frete = $novo;
        }

        $this->valorAutomatico = $novo;
    }

    /** Seleciona o local recém-cadastrado pelo modal "Novo local". */
    #[On('local-criado')]
    public function selecionarLocalCriado(int $id, ?string $alvo = null): void
    {
        if ($alvo === 'origem') {
            $this->form->origem_local_id = (string) $id;
        } elseif ($alvo === 'destino') {
            $this->form->destino_local_id = (string) $id;
        }

        $this->sugerirFrete();
    }

    public function salvar(): void
    {
        Gate::authorize('create', Viagem::class);

        $this->form->criar();

        $this->toastNaProximaPagina('Viagem lançada.');

        $this->redirectRoute('viagens.index', navigate: true);
    }

    /** Comissão calculada na hora a partir do frete e da porcentagem (só exibição). */
    public function comissaoPrevista(): ?string
    {
        $valor = Numero::paraDecimal($this->form->valor_frete);
        $percentual = Numero::paraDecimal($this->form->comissao_percentual);

        return $valor !== null && $percentual !== null ? Dinheiro::percentual($valor, $percentual) : null;
    }

    /** @return array<string, mixed> */
    protected function opcoes(): array
    {
        $atual = $this->form->viagem;

        $motorista = $this->form->motorista_id !== '' ? Motorista::query()->find((int) $this->form->motorista_id) : null;

        return [
            'motoristas' => Motorista::query()->with('filial:id,codigo')
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhere('id', $atual->motorista_id)))
                ->orderBy('nome')->get(['id', 'nome', 'filial_id']),
            'veiculos' => $motorista === null ? collect() : Veiculo::query()
                ->where('filial_id', $motorista->filial_id)
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhere('id', $atual->veiculo_id)))
                ->orderBy('placa')->get(['id', 'placa', 'tipo']),
            'locais' => Local::query()
                ->where(fn ($q) => $q->where('ativo', true)->when($atual, fn ($c) => $c->orWhereIn('id', [$atual->origem_local_id, $atual->destino_local_id])))
                ->orderBy('nome')->get(['id', 'nome']),
            'statusOpcoes' => ViagemStatus::editaveis(),
        ];
    }

    public function render(): View
    {
        return view('livewire.viagens.create', $this->opcoes());
    }
}
