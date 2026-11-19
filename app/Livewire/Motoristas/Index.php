<?php

namespace App\Livewire\Motoristas;

use App\Actions\Motoristas\CriarAcessoDoMotorista;
use App\Actions\Motoristas\RedefinirSenhaDoMotorista;
use App\Livewire\Concerns\ListaCadastro;
use App\Models\Motorista;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Motoristas')]
class Index extends Component
{
    use ListaCadastro;

    protected function modelo(): string
    {
        return Motorista::class;
    }

    protected function consulta(): Builder
    {
        return Motorista::query()->with('filial:id,nome,codigo');
    }

    protected function rotulo(object $registro): string
    {
        return "Motorista {$registro->nome}";
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'cpf', 'comissao_percentual', 'ativo'];
    }

    protected function ordenacaoPadrao(): string
    {
        return 'nome';
    }

    /** RN-21: cria o login do portal (CPF como login e senha inicial). */
    public function criarAcesso(int $id): void
    {
        $motorista = Motorista::query()->findOrFail($id);

        Gate::authorize('criarAcesso', $motorista);

        try {
            app(CriarAcessoDoMotorista::class)->handle($motorista, Auth::user());
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast("Acesso criado: {$motorista->nome} entra com o CPF como login e senha.");
    }

    public function redefinirSenha(int $id): void
    {
        $motorista = Motorista::query()->findOrFail($id);

        Gate::authorize('redefinirSenha', $motorista);

        try {
            app(RedefinirSenhaDoMotorista::class)->handle($motorista, Auth::user());
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'erro');

            return;
        }

        $this->toast("Senha de {$motorista->nome} voltou a ser o CPF.");
    }

    public function render(): View
    {
        return view('livewire.motoristas.index', [
            'motoristas' => $this->paginar($this->consulta(), ['nome', 'telefone'], ['cpf']),
        ]);
    }
}
