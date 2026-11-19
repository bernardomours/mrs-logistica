<?php

namespace App\Livewire\Relatorios;

use App\Services\Relatorios\Catalogo;
use App\Services\Relatorios\ComCartoes;
use App\Services\Relatorios\ComDetalhes;
use App\Services\Relatorios\Relatorio;
use App\Services\Relatorios\Tabela;
use App\Support\PeriodoRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Um relatório com filtro de período e exportação para Excel. A consulta respeita o escopo de filial (RN-18). */
#[Layout('components.layouts.app')]
#[Title('Relatório')]
class Show extends Component
{
    public string $relatorio;

    #[Url(history: true)]
    public string $periodo = 'mes_atual';

    #[Url(history: true)]
    public string $de = '';

    #[Url(history: true)]
    public string $ate = '';

    public function mount(string $relatorio): void
    {
        $this->relatorio = $relatorio;

        $this->definicao();
    }

    private function definicao(): Relatorio
    {
        $catalogo = app(Catalogo::class);
        $definicao = $catalogo->porChave($this->relatorio) ?? abort(404);

        abort_unless($catalogo->podeVer(Auth::user(), $definicao), 403);

        return $definicao;
    }

    public function updatedPeriodo(): void
    {
        // Ao escolher "personalizado" pela primeira vez, parte do mês atual.
        if ($this->periodo === 'personalizado' && $this->de === '' && $this->ate === '') {
            $mes = PeriodoRelatorio::deAtalho('mes_atual');
            $this->de = $mes->de();
            $this->ate = $mes->ate();
        }
    }

    public function render(): View
    {
        $definicao = $this->definicao();
        $atalho = array_key_exists($this->periodo, PeriodoRelatorio::ATALHOS) ? $this->periodo : 'mes_atual';
        $periodo = PeriodoRelatorio::deAtalho($atalho, $this->de, $this->ate);
        $linhas = $definicao->linhas($periodo);

        return view('livewire.relatorios.show', [
            'definicao' => $definicao,
            'periodoEscolhido' => $periodo,
            'atalhos' => PeriodoRelatorio::ATALHOS,
            'colunas' => $definicao->colunas(),
            'linhas' => $linhas,
            'cartoes' => $definicao instanceof ComCartoes && $linhas->isNotEmpty() ? $definicao->cartoes($linhas) : null,
            'detalhes' => $definicao instanceof ComDetalhes && $linhas->isNotEmpty() ? $definicao->detalhes($periodo, $linhas) : null,
            'totais' => $linhas->count() > 1 ? Tabela::totais($definicao->colunas(), $linhas) : null,
            'podeExportar' => Auth::user()->can('relatorios.exportar'),
            'atalhoAtual' => $atalho,
        ]);
    }
}
