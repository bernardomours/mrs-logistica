<?php

namespace App\Livewire\Dashboard;

use App\Services\AlertasDePreventiva;
use App\Services\FilialAtual;
use App\Services\Indicadores;
use App\Services\Relatorios\ConsumoPorVeiculo;
use App\Services\Relatorios\FaturamentoPorRota;
use App\Services\Relatorios\Inconsistencias;
use App\Support\PeriodoRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Index extends Component
{
    #[Url(history: true)]
    public string $periodo = 'mes_atual';

    #[Url(history: true)]
    public string $de = '';

    #[Url(history: true)]
    public string $ate = '';

    /** Filial do filtro: id ou "todas". É a mesma filial ativa do seletor do menu (RN-18). */
    public string $filial = 'todas';

    public function mount(FilialAtual $filiais): void
    {
        // Motorista não usa o painel administrativo: vai direto para o portal (RN-21).
        if (Auth::user()->hasRole('motorista')) {
            $this->redirectRoute('portal.dashboard');

            return;
        }

        $this->filial = (string) ($filiais->selecionada() ?? 'todas');
    }

    /**
     * Trocar a filial aqui troca a filial ativa do sistema inteiro (a mesma do menu lateral), sempre
     * dentro das filiais que o usuário acessa. A página recarrega para todos os componentes respeitarem o escopo.
     */
    public function updatedFilial(string $valor, FilialAtual $filiais): void
    {
        $filiais->selecionar($valor === 'todas' ? null : (int) $valor);

        $this->js('window.location.reload()');
    }

    public function updatedPeriodo(): void
    {
        if ($this->periodo === 'personalizado' && $this->de === '' && $this->ate === '') {
            $mes = PeriodoRelatorio::deAtalho('mes_atual');
            $this->de = $mes->de();
            $this->ate = $mes->ate();
        }
    }

    public function render(): View
    {
        $usuario = Auth::user();

        if (! $usuario->can('dashboard.ver')) {
            return view('livewire.dashboard.index', ['completo' => false]);
        }

        $atalho = array_key_exists($this->periodo, PeriodoRelatorio::ATALHOS) ? $this->periodo : 'mes_atual';
        $periodo = PeriodoRelatorio::deAtalho($atalho, $this->de, $this->ate);
        $indicadores = app(Indicadores::class);
        $filiais = app(FilialAtual::class);
        $disponiveis = $filiais->disponiveis();

        return view('livewire.dashboard.index', [
            'completo' => true,
            'filiaisDisponiveis' => $disponiveis,
            'podeVerTodas' => $filiais->permitidas() === null || $disponiveis->count() > 1,
            'filialAtual' => $filiais->selecionada() !== null ? $disponiveis->firstWhere('id', $filiais->selecionada()) : null,
            'periodoEscolhido' => $periodo,
            'atalhos' => PeriodoRelatorio::ATALHOS,
            'atalhoAtual' => $atalho,
            'parcial' => $indicadores->emAndamento($periodo),
            'kpis' => $indicadores->paraPeriodo($periodo),
            'topRotas' => $usuario->can('viagens.ver_valores') ? app(FaturamentoPorRota::class)->linhas($periodo)->take(5) : collect(),
            'consumo' => $usuario->can('abastecimentos.ver') ? app(ConsumoPorVeiculo::class)->linhas($periodo)->sortByDesc(fn ($l) => (float) $l['litros'])->take(5)->values() : collect(),
            'preventivas' => $usuario->can('manutencoes.ver') ? app(AlertasDePreventiva::class)->handle() : collect(),
            'inconsistencias' => $usuario->can('relatorios.ver') ? app(Inconsistencias::class)->linhas($periodo)->count() : null,
            'semanasPendentes' => $usuario->can('fechamentos.ver') ? $indicadores->semanasSemFechamento() : null,
        ]);
    }
}
