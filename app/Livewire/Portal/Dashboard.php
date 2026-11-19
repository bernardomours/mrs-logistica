<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ComoPortalDoMotorista;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dashboard do motorista (RN-21): panorama da semana (padrão: a atual) ou do mês, somente leitura.
 * O seletor de semana é o mesmo do fechamento semanal (◀ semana ▶).
 */
#[Layout('components.layouts.portal')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use ComoPortalDoMotorista;

    /** "semana" ou "mes". */
    #[Url(history: true)]
    public string $visao = 'semana';

    /** Chave ISO ("2026-W42"); vazia = semana atual. */
    #[Url(history: true)]
    public string $semana = '';

    /** "2026-11"; vazio = mês atual. */
    #[Url(history: true)]
    public string $mes = '';

    public function mount(): void
    {
        $this->autorizarPortal();
    }

    public function definirVisao(string $visao): void
    {
        $this->visao = $visao === 'mes' ? 'mes' : 'semana';
    }

    public function irParaMes(string $valor): void
    {
        $this->mes = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor) ? $valor : '';
    }

    public function irParaSemana(string $chave): void
    {
        $this->semana = SemanaIso::daChave($chave)?->chave() ?? '';
    }

    public function render(): View
    {
        $motorista = $this->motorista();
        $resumos = app(ResumoSemanal::class);
        $porMes = $this->visao === 'mes';

        $dados = ['nome' => $motorista->nome, 'porMes' => $porMes];

        if ($porMes) {
            $mes = $this->mesEscolhido($this->mes);
            $atual = CarbonImmutable::today()->startOfMonth();

            return view('livewire.portal.dashboard', $dados + [
                'mesResumo' => $resumos->paraPortalNoMes($motorista, $mes),
                'mesChave' => $mes->format('Y-m'),
                'mesAnterior' => $mes->subMonthNoOverflow()->format('Y-m'),
                'mesProximo' => $mes->addMonthNoOverflow()->format('Y-m'),
                'ehMesAtual' => $mes->equalTo($atual),
                'mesAtualChave' => $atual->format('Y-m'),
            ]);
        }

        $semana = $this->semanaEscolhida($this->semana);
        $atual = SemanaIso::atual();

        return view('livewire.portal.dashboard', $dados + [
            'resumo' => $resumos->paraPortal($motorista, $semana),
            'ehAtual' => $semana->chave() === $atual->chave(),
            'anterior' => $semana->anterior()->chave(),
            'proxima' => $semana->proxima()->chave(),
            'semanaAtualChave' => $atual->chave(),
        ]);
    }
}
