<?php

namespace App\Livewire\Fechamentos;

use App\Enums\ViagemStatus;
use App\Models\Abastecimento;
use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Models\Scopes\FilialScope;
use App\Models\Viagem;
use App\Support\Dinheiro;
use App\Support\SemanaIso;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Visão da semana: todos os motoristas com movimento e a situação do acerto de cada um (em andamento,
 * fechada ou paga). O fechamento em si é feito na folha do motorista (`Folha`).
 */
#[Layout('components.layouts.app')]
#[Title('Fechamento semanal')]
class Index extends Component
{
    use WithPagination;

    /** Chave ISO ("2026-W42"); vazia = semana anterior, a que normalmente está sendo fechada. */
    #[Url(history: true)]
    public string $semana = '';

    #[Url(as: 'q', history: true)]
    public string $busca = '';

    /** todas | em_andamento | fechado | pago */
    #[Url(history: true)]
    public string $situacao = 'todas';

    public function mount(): void
    {
        Gate::authorize('viewAny', FechamentoSemanal::class);
    }

    private function semanaIso(): SemanaIso
    {
        return SemanaIso::daChave($this->semana) ?? SemanaIso::atual()->anterior();
    }

    public function irParaSemana(string $chave): void
    {
        if (SemanaIso::daChave($chave) !== null) {
            $this->semana = $chave;
            $this->resetPage();
        }
    }

    public function updatedBusca(): void
    {
        $this->resetPage();
    }

    public function updatedSituacao(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->busca = '';
        $this->situacao = 'todas';
        $this->resetPage();
    }

    public function render(): View
    {
        $semana = $this->semanaIso();
        $intervalo = [$semana->inicio->toDateString(), $semana->fim->toDateString()];

        // Totais por motorista direto no banco (só somas simples; médias e fechamento ficam na folha).
        $viagens = Viagem::withoutGlobalScope(FilialScope::class)
            ->whereBetween('data_viagem', $intervalo)
            ->whereIn('motorista_id', Motorista::query()->select('id'))
            ->selectRaw('motorista_id, count(*) as lancadas')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as concluidas', [ViagemStatus::Concluida->value])
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as pendentes', [ViagemStatus::Planejada->value, ViagemStatus::EmTransito->value])
            ->selectRaw('coalesce(sum(case when status = ? then valor_frete else 0 end), 0) as frete', [ViagemStatus::Concluida->value])
            ->selectRaw('coalesce(sum(case when status = ? then comissao_valor else 0 end), 0) as comissao', [ViagemStatus::Concluida->value])
            ->groupBy('motorista_id')->get()->keyBy('motorista_id');

        $abastecimentos = Abastecimento::withoutGlobalScope(FilialScope::class)
            ->whereBetween('data_abastecimento', $intervalo)
            ->whereIn('motorista_id', Motorista::query()->select('id'))
            ->selectRaw('motorista_id, count(*) as lancados, coalesce(sum(valor_total), 0) as valor')
            ->groupBy('motorista_id')->get()->keyBy('motorista_id');

        $fechamentos = FechamentoSemanal::query()->daSemana($semana)->get()->keyBy('motorista_id');

        $comMovimento = $viagens->keys()->merge($abastecimentos->keys())->merge($fechamentos->keys())->unique();

        $idsDaSituacao = $comMovimento->filter(fn ($id) => match ($this->situacao) {
            'em_andamento' => ! $fechamentos->has($id),
            'fechado', 'pago' => $fechamentos->get($id)?->status->value === $this->situacao,
            default => true,
        })->values();

        $motoristas = Motorista::query()
            ->with('filial:id,codigo')
            ->whereIn('id', $idsDaSituacao)
            ->when($this->busca !== '', fn ($q) => $q->where('nome', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim($this->busca)).'%'))
            ->orderBy('nome')
            ->paginate(20);

        return view('livewire.fechamentos.index', [
            'semanaIso' => $semana,
            'motoristas' => $motoristas,
            'viagens' => $viagens,
            'abastecimentos' => $abastecimentos,
            'fechamentos' => $fechamentos,
            'resumoGeral' => [
                'movimento' => $comMovimento->count(),
                'em_andamento' => $comMovimento->count() - $fechamentos->count(),
                'fechadas' => $fechamentos->filter(fn ($f) => ! $f->estaPago())->count(),
                'pagas' => $fechamentos->filter(fn ($f) => $f->estaPago())->count(),
                'a_pagar' => Dinheiro::total($fechamentos->filter(fn ($f) => ! $f->estaPago())->pluck('valor_a_pagar')),
            ],
            'temFiltros' => $this->busca !== '' || $this->situacao !== 'todas',
        ]);
    }
}
