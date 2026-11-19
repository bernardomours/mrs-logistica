<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ComoPortalDoMotorista;
use App\Services\ResumoSemanal;
use App\Support\SemanaIso;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Abastecimentos do motorista na semana escolhida (padrão: a atual), somente leitura. */
#[Layout('components.layouts.portal')]
#[Title('Abastecimentos')]
class Abastecimentos extends Component
{
    use ComoPortalDoMotorista;

    /** Chave ISO ("2026-W42"); vazia = semana atual. */
    #[Url(history: true)]
    public string $semana = '';

    public function mount(): void
    {
        $this->autorizarPortal();
    }

    public function irParaSemana(string $chave): void
    {
        $this->semana = SemanaIso::daChave($chave)?->chave() ?? '';
    }

    public function render(): View
    {
        $semana = $this->semanaEscolhida($this->semana);
        $atual = SemanaIso::atual();

        return view('livewire.portal.abastecimentos', [
            'resumo' => app(ResumoSemanal::class)->paraPortal($this->motorista(), $semana),
            'ehAtual' => $semana->chave() === $atual->chave(),
            'anterior' => $semana->anterior()->chave(),
            'proxima' => $semana->proxima()->chave(),
            'semanaAtualChave' => $atual->chave(),
        ]);
    }
}
