<?php

namespace App\Livewire\Motoristas;

use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal "Relatório semanal" da lista de motoristas: escolhe a semana e baixa o PDF da folha do motorista.
 * Abre com o evento `abrir-relatorio-semanal` (id do motorista). Só leitura: nada é gravado aqui.
 */
class RelatorioSemanal extends Component
{
    private const SEMANAS_NO_SELETOR = 26;

    public bool $aberto = false;

    public ?int $motoristaId = null;

    /** Chave ISO ("2026-W42"); padrão = semana anterior, a que normalmente se entrega ao motorista. */
    public string $semana = '';

    #[On('abrir-relatorio-semanal')]
    public function abrir(int $motoristaId): void
    {
        $motorista = Motorista::query()->findOrFail($motoristaId);

        Gate::authorize('exportar', [FechamentoSemanal::class, $motorista]);

        $this->motoristaId = $motorista->id;
        $this->semana = SemanaIso::atual()->anterior()->chave();
        $this->aberto = true;
    }

    public function fechar(): void
    {
        $this->aberto = false;
        $this->motoristaId = null;
    }

    public function render(): View
    {
        $motorista = $this->aberto && $this->motoristaId !== null ? Motorista::query()->find($this->motoristaId) : null;
        $semanaIso = SemanaIso::daChave($this->semana) ?? SemanaIso::atual()->anterior();
        $atual = SemanaIso::atual();

        $semanas = collect(range(0, self::SEMANAS_NO_SELETOR - 1))->map(function (int $i) use ($atual) {
            $s = SemanaIso::deData($atual->inicio->subWeeks($i));

            return ['chave' => $s->chave(), 'rotulo' => 'Semana '.$s->semana.' · '.$s->inicio->format('d/m').' a '.$s->fim->format('d/m/Y').($i === 0 ? ' (atual)' : '')];
        });

        $resumo = $motorista !== null && Gate::allows('exportar', [FechamentoSemanal::class, $motorista])
            ? app(ResumoSemanal::class)->paraMotorista($motorista, $semanaIso)
            : null;

        return view('livewire.motoristas.relatorio-semanal', [
            'motorista' => $motorista,
            'semanaIso' => $semanaIso,
            'semanas' => $semanas,
            'resumo' => $resumo,
        ]);
    }
}
