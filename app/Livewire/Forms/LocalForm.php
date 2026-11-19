<?php

namespace App\Livewire\Forms;

use App\Actions\Locais\CriarLocal;
use App\Enums\LocalTipo;
use App\Models\Local;
use App\Support\Texto;
use App\Support\Uf;
use Illuminate\Validation\Rule;

/** Cadastro rápido de local (botão "Novo local" em Tarifas de rota). */
class LocalForm extends FormularioBase
{
    public string $nome = '';

    public string $tipo = '';

    public string $cidade = '';

    public string $uf = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:150',
                fn (string $atributo, mixed $valor, \Closure $falhar) => Local::query()
                    ->where('nome_chave', Texto::chave((string) $valor))
                    ->exists() ? $falhar('Já existe um local com este nome.') : null,
            ],
            'tipo' => ['nullable', Rule::enum(LocalTipo::class)],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', Rule::in(Uf::siglas())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['uf' => 'UF'];
    }

    public function criar(): Local
    {
        $this->validate();

        $local = $this->executar(fn () => app(CriarLocal::class)->handle([
            'nome' => trim($this->nome),
            'tipo' => $this->tipo !== '' ? $this->tipo : null,
            'cidade' => $this->cidade !== '' ? trim($this->cidade) : null,
            'uf' => $this->uf !== '' ? $this->uf : null,
            'ativo' => true,
        ]));

        $this->reset();

        return $local;
    }
}
