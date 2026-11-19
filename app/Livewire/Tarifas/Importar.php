<?php

namespace App\Livewire\Tarifas;

use App\Actions\Tarifas\AnalisarPlanilhaTarifas;
use App\Actions\Tarifas\ImportarTarifas;
use App\Livewire\Concerns\EmiteToast;
use App\Models\TarifaRota;
use App\Services\PlanilhaTarifas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Importar tarifas de rota')]
class Importar extends Component
{
    use EmiteToast, WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $arquivo = null;

    public string $vigencia_inicio = '';

    public bool $encerrar_ausentes = false;

    /** Resultado da análise (somente para exibir; a importação reanalisa no servidor). */
    public ?array $analise = null;

    /** Filtro da pré-visualização: todas | mudancas | erros. */
    public string $mostrar = 'mudancas';

    public function mount(): void
    {
        Gate::authorize('importar', TarifaRota::class);

        $this->vigencia_inicio = now()->toDateString();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:5120'],
            'vigencia_inicio' => ['required', 'date'],
            'encerrar_ausentes' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'arquivo' => 'planilha',
            'vigencia_inicio' => 'início da vigência',
        ];
    }

    /** Qualquer mudança nos parâmetros invalida a pré-visualização anterior. */
    public function updated(string $propriedade): void
    {
        if (in_array($propriedade, ['arquivo', 'vigencia_inicio', 'encerrar_ausentes'], true)) {
            $this->analise = null;
        }

        if ($propriedade === 'arquivo') {
            $this->resetErrorBag('arquivo');
        }
    }

    public function analisar(): void
    {
        Gate::authorize('importar', TarifaRota::class);
        $this->validate();

        $linhas = app(PlanilhaTarifas::class)->ler($this->arquivo->getRealPath(), $this->arquivo->getClientOriginalExtension());

        $this->analise = app(AnalisarPlanilhaTarifas::class)->handle(
            $linhas,
            null,
            $this->vigencia_inicio,
            $this->encerrar_ausentes,
        );

        $this->mostrar = $this->analise['resumo']['erro'] > 0 && $this->totalDeMudancas() === 0 ? 'erros' : 'mudancas';
    }

    public function confirmar(): void
    {
        Gate::authorize('importar', TarifaRota::class);
        $this->validate();

        $linhas = app(PlanilhaTarifas::class)->ler($this->arquivo->getRealPath(), $this->arquivo->getClientOriginalExtension());

        $resumo = app(ImportarTarifas::class)->handle(
            $linhas,
            null,
            $this->vigencia_inicio,
            $this->encerrar_ausentes,
            Auth::user(),
        );

        $partes = array_filter([
            $resumo['criadas'] ? "{$resumo['criadas']} criada(s)" : null,
            $resumo['atualizadas'] ? "{$resumo['atualizadas']} atualizada(s)" : null,
            $resumo['encerradas'] ? "{$resumo['encerradas']} encerrada(s)" : null,
            $resumo['sem_alteracao'] ? "{$resumo['sem_alteracao']} sem alteração" : null,
            $resumo['locais_criados'] ? "{$resumo['locais_criados']} local(is) novo(s)" : null,
            $resumo['erros'] ? "{$resumo['erros']} linha(s) ignorada(s) por erro" : null,
        ]);

        $this->toastNaProximaPagina('Importação concluída: '.($partes === [] ? 'nada para alterar.' : implode(', ', $partes).'.'));

        $this->redirectRoute('tarifas.index', navigate: true);
    }

    public function cancelar(): void
    {
        $this->reset('arquivo', 'analise');
        $this->resetErrorBag();
    }

    public function totalDeMudancas(): int
    {
        $resumo = $this->analise['resumo'] ?? [];

        return ($resumo['criar'] ?? 0) + ($resumo['atualizar'] ?? 0) + ($resumo['a_encerrar'] ?? 0);
    }

    public function render(): View
    {
        $linhas = collect($this->analise['linhas'] ?? [])
            ->when($this->mostrar === 'mudancas', fn ($c) => $c->whereIn('acao', ['criar', 'atualizar']))
            ->when($this->mostrar === 'erros', fn ($c) => $c->where('acao', 'erro'))
            ->values();

        return view('livewire.tarifas.importar', [
            'linhasVisiveis' => $linhas->take(300),
            'linhasOcultas' => max(0, $linhas->count() - 300),
        ]);
    }
}
