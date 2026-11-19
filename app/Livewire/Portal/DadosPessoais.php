<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ComoPortalDoMotorista;
use App\Services\PerfilDoMotorista;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Dados do motorista como o administrador cadastrou, a filial e o veículo ligado a ele (RN-21). Somente leitura. */
#[Layout('components.layouts.portal')]
#[Title('Dados pessoais')]
class DadosPessoais extends Component
{
    use ComoPortalDoMotorista;

    public function mount(): void
    {
        $this->autorizarPortal();
    }

    public function render(): View
    {
        return view('livewire.portal.dados-pessoais', app(PerfilDoMotorista::class)->para($this->motorista()));
    }
}
