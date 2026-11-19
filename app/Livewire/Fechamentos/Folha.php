<?php

namespace App\Livewire\Fechamentos;

use App\Actions\Fechamentos\EstornarPagamento;
use App\Actions\Fechamentos\FecharSemana;
use App\Actions\Fechamentos\MarcarPago;
use App\Actions\Fechamentos\ReabrirSemana;
use App\Livewire\Concerns\EmiteToast;
use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Services\ResumoDaSemana;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A tela-planilha do acerto semanal de um motorista (CLAUDE.md §9, tela 2): viagens, abastecimentos,
 * totais, médias e as ações fechar / pagar / estornar / reabrir. Mesmos números da planilha original.
 */
#[Layout('components.layouts.app')]
#[Title('Folha da semana')]
class Folha extends Component
{
    use EmiteToast;

    public int $motoristaId;

    /** Chave ISO da semana, ex.: "2026-W42". */
    public string $semana;

    /** Ação que está pedindo motivo: "reabrir" ou "estornar". */
    public ?string $acaoPendente = null;

    public string $motivo = '';

    /** Data do pagamento ao marcar como pago (yyyy-mm-dd). */
    public string $pagoEm = '';

    public function mount(Motorista $motorista, string $semana): void
    {
        Gate::authorize('viewAny', FechamentoSemanal::class);

        abort_if(SemanaIso::daChave($semana) === null, 404);

        $this->motoristaId = $motorista->id;
        $this->semana = $semana;
        $this->pagoEm = CarbonImmutable::today()->toDateString();
    }

    private function motorista(): Motorista
    {
        return Motorista::query()->with('filial:id,nome,codigo')->findOrFail($this->motoristaId);
    }

    private function semanaIso(): SemanaIso
    {
        return SemanaIso::daChave($this->semana) ?? abort(404);
    }

    private function resumo(): ResumoDaSemana
    {
        return app(ResumoSemanal::class)->paraMotorista($this->motorista(), $this->semanaIso());
    }

    public function trocarMotorista(string $id): void
    {
        $motorista = Motorista::query()->findOrFail((int) $id);

        $this->redirectRoute('fechamentos.folha', ['motorista' => $motorista->id, 'semana' => $this->semana], navigate: true);
    }

    public function fechar(): void
    {
        $motorista = $this->motorista();

        Gate::authorize('fechar', [FechamentoSemanal::class, $motorista]);

        app(FecharSemana::class)->handle($motorista, $this->semanaIso(), Auth::user());

        $this->toast('Semana fechada. Viagens e abastecimentos dela ficam somente para consulta.');
    }

    public function marcarPago(): void
    {
        $fechamento = $this->fechamentoOuFalha();

        Gate::authorize('pagar', $fechamento);

        $this->validate(['pagoEm' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']], attributes: ['pagoEm' => 'data do pagamento']);

        app(MarcarPago::class)->handle($fechamento, Auth::user(), CarbonImmutable::parse($this->pagoEm)->setTimeFrom(now()));

        $this->toast('Pagamento registrado.');
    }

    public function pedirMotivo(string $acao): void
    {
        abort_unless(in_array($acao, ['reabrir', 'estornar'], true), 404);

        $this->resetErrorBag();
        $this->acaoPendente = $acao;
        $this->motivo = '';
    }

    public function cancelarMotivo(): void
    {
        $this->acaoPendente = null;
        $this->motivo = '';
        $this->resetErrorBag();
    }

    public function confirmarMotivo(): void
    {
        $fechamento = $this->fechamentoOuFalha();

        if ($this->acaoPendente === 'reabrir') {
            Gate::authorize('reabrir', $fechamento);
            app(ReabrirSemana::class)->handle($fechamento, Auth::user(), $this->motivo);
            $this->toast('Semana reaberta. Os lançamentos voltam a poder ser alterados.');
        } elseif ($this->acaoPendente === 'estornar') {
            Gate::authorize('estornar', $fechamento);
            app(EstornarPagamento::class)->handle($fechamento, Auth::user(), $this->motivo);
            $this->toast('Pagamento estornado.');
        } else {
            return;
        }

        $this->cancelarMotivo();
    }

    private function fechamentoOuFalha(): FechamentoSemanal
    {
        return FechamentoSemanal::query()
            ->where('motorista_id', $this->motoristaId)->daSemana($this->semanaIso())
            ->firstOrFail();
    }

    public function render(): View
    {
        $semana = $this->semanaIso();
        $resumo = $this->resumo();

        return view('livewire.fechamentos.folha', [
            'resumo' => $resumo,
            'semanaIso' => $semana,
            'motorista' => $resumo->motorista,
            // Semana fechada mostra o snapshot gravado; em aberto, o cálculo ao vivo.
            'totais' => $resumo->totais(),
            'motoristas' => Motorista::query()->orderBy('nome')->get(['id', 'nome']),
            'futura' => $semana->inicio->greaterThan(CarbonImmutable::today()),
        ]);
    }
}
