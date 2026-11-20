<?php

namespace App\Livewire\Forms;

use App\Actions\Papeis\AtualizarPapel;
use App\Actions\Papeis\CriarPapel;
use App\Models\User;
use Spatie\Permission\Models\Role;

class PapelForm extends FormularioBase
{
    public ?Role $papel = null;

    public string $nome = '';

    /** @var array<int, string> */
    public array $permissoes = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'permissoes' => ['array'],
            'permissoes.*' => ['string'],
        ];
    }

    public function preencher(Role $papel): void
    {
        $this->papel = $papel;
        $this->nome = $papel->name;
        $this->permissoes = $papel->permissions()->pluck('name')->all();
    }

    public function criar(User $ator): Role
    {
        $this->validate();

        return $this->executar(fn () => app(CriarPapel::class)->handle($ator, $this->nome, $this->permissoes));
    }

    public function atualizar(User $ator): Role
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarPapel::class)->handle($ator, $this->papel, $this->nome, $this->permissoes));
    }
}
