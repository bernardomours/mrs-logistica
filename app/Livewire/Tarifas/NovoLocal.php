<?php

namespace App\Livewire\Tarifas;

use App\Enums\LocalTipo;
use App\Livewire\Concerns\EmiteToast;
use App\Livewire\Forms\LocalForm;
use App\Models\Local;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal de cadastro rápido de local, usado dentro de Tarifas de rota.
 * Abre com o evento `abrir-novo-local` (opcionalmente `alvo`: origem|destino) e
 * avisa com `local-criado` para a tela de origem selecionar o novo local.
 */
class NovoLocal extends Component
{
    use EmiteToast;

    public LocalForm $form;

    public bool $aberto = false;

    public ?string $alvo = null;

    #[On('abrir-novo-local')]
    public function abrir(?string $alvo = null): void
    {
        Gate::authorize('create', Local::class);

        $this->form->reset();
        $this->resetErrorBag();
        $this->alvo = $alvo;
        $this->aberto = true;
    }

    public function fechar(): void
    {
        $this->aberto = false;
    }

    public function salvar(): void
    {
        Gate::authorize('create', Local::class);

        $local = $this->form->criar();

        $this->aberto = false;
        $this->dispatch('local-criado', id: $local->id, alvo: $this->alvo);
        $this->toast("Local {$local->nome} cadastrado.");
    }

    public function render(): View
    {
        return view('livewire.tarifas.novo-local', ['tipos' => LocalTipo::cases()]);
    }
}
