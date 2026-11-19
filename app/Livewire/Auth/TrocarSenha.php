<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\EmiteToast;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Trocar senha')]
class TrocarSenha extends Component
{
    use EmiteToast;

    public string $senha_atual = '';

    public string $nova_senha = '';

    public string $nova_senha_confirmation = '';

    public bool $obrigatoria = false;

    public function mount(): void
    {
        $this->obrigatoria = (bool) Auth::user()->deve_trocar_senha;
    }

    protected function rules(): array
    {
        return [
            'senha_atual' => ['required', 'current_password'],
            'nova_senha' => ['required', 'confirmed', 'different:senha_atual', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'senha_atual' => 'senha atual',
            'nova_senha' => 'nova senha',
            'nova_senha_confirmation' => 'confirmação da nova senha',
        ];
    }

    public function salvar(): void
    {
        $this->validate();

        $usuario = Auth::user();
        $usuario->forceFill(['password' => $this->nova_senha, 'deve_trocar_senha' => false])->save();

        $this->toastNaProximaPagina('Senha alterada com sucesso.');

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.auth.trocar-senha');
    }
}
