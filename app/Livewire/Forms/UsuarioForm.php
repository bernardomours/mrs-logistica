<?php

namespace App\Livewire\Forms;

use App\Actions\Usuarios\AtualizarUsuario;
use App\Actions\Usuarios\CriarUsuario;
use App\Models\User;
use App\Rules\Cpf;
use App\Support\Texto;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioForm extends FormularioBase
{
    public ?User $usuario = null;

    public string $name = '';

    public string $email = '';

    public string $cpf = '';

    public string $papel = '';

    /** @var array<int, int|string> */
    public array $filiais = [];

    public string $filial_padrao_id = '';

    public bool $ativo = true;

    public string $senha = '';

    public bool $deve_trocar_senha = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users', 'email')->ignore($this->usuario?->getKey())],
            'cpf' => [
                'nullable', new Cpf,
                fn (string $atributo, mixed $valor, \Closure $falhar) => User::query()
                    ->where('cpf', Texto::digitos((string) $valor))
                    ->when($this->usuario, fn ($q) => $q->whereKeyNot($this->usuario->getKey()))
                    ->exists() ? $falhar('Já existe um usuário com este CPF.') : null,
            ],
            'papel' => ['required', 'string'],
            'filiais' => ['array'],
            'filiais.*' => ['integer'],
            'filial_padrao_id' => ['nullable', 'integer'],
            'ativo' => ['boolean'],
            'senha' => [$this->usuario ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'deve_trocar_senha' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'name' => 'nome', 'email' => 'e-mail', 'cpf' => 'CPF', 'papel' => 'papel',
            'filial_padrao_id' => 'filial padrão', 'senha' => 'senha', 'filiais' => 'filiais',
        ];
    }

    public function preencher(User $usuario): void
    {
        $this->usuario = $usuario;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->cpf = (string) $usuario->cpf;
        $this->papel = (string) $usuario->getRoleNames()->first();
        $this->filiais = $usuario->filiais()->pluck('filiais.id')->map(fn ($id) => (string) $id)->all();
        $this->filial_padrao_id = (string) $usuario->filial_padrao_id;
        $this->ativo = $usuario->ativo;
        $this->deve_trocar_senha = $usuario->deve_trocar_senha;
    }

    public function criar(User $ator): User
    {
        $this->validate();

        return $this->executar(fn () => app(CriarUsuario::class)->handle($ator, $this->dados()));
    }

    public function atualizar(User $ator): User
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarUsuario::class)->handle($ator, $this->usuario, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'name' => trim($this->name),
            'email' => trim($this->email),
            'cpf' => $this->cpf !== '' ? Texto::digitos($this->cpf) : null,
            'papel' => $this->papel,
            'filiais' => array_values(array_unique(array_map('intval', $this->filiais))),
            'filial_padrao_id' => $this->filial_padrao_id !== '' ? (int) $this->filial_padrao_id : null,
            'ativo' => $this->ativo,
            'senha' => $this->senha !== '' ? $this->senha : null,
            'deve_trocar_senha' => $this->deve_trocar_senha,
        ];
    }
}
