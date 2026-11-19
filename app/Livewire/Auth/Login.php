<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Entrar')]
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $lembrar = false;

    protected function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['login' => 'e-mail ou CPF', 'password' => 'senha'];
    }

    public function entrar(): void
    {
        $this->validate();

        $chave = Str::transliterate(Str::lower($this->login).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($chave, 5)) {
            $segundos = RateLimiter::availableIn($chave);

            throw ValidationException::withMessages([
                'login' => "Muitas tentativas. Tente novamente em {$segundos} segundos.",
            ]);
        }

        $porEmail = str_contains($this->login, '@');
        $credenciais = $porEmail
            ? ['email' => trim($this->login)]
            : ['cpf' => preg_replace('/\D/', '', $this->login)];

        if (! Auth::attempt($credenciais + ['password' => $this->password, 'ativo' => true], $this->lembrar)) {
            RateLimiter::hit($chave, 60);

            throw ValidationException::withMessages([
                'login' => 'Credenciais inválidas ou acesso desativado.',
            ]);
        }

        /** @var User $usuario */
        $usuario = Auth::user();

        // E-mail é a porta da equipe (admin e demais perfis administrativos); CPF é a porta do motorista.
        if ($porEmail === $usuario->hasRole('motorista')) {
            Auth::logout();
            RateLimiter::hit($chave, 60);

            throw ValidationException::withMessages([
                'login' => $porEmail
                    ? 'Motorista entra com o CPF, não com e-mail.'
                    : 'O CPF é só para motoristas. A equipe entra com o e-mail.',
            ]);
        }

        RateLimiter::clear($chave);
        session()->regenerate();
        $usuario->forceFill(['ultimo_login_at' => now()])->saveQuietly();

        // Motorista entra direto no portal; os demais, no painel.
        $this->redirectIntended(route($usuario->hasRole('motorista') ? 'portal.dashboard' : 'dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
