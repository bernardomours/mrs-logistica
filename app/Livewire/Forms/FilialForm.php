<?php

namespace App\Livewire\Forms;

use App\Actions\Filiais\AtualizarFilial;
use App\Actions\Filiais\CriarFilial;
use App\Models\Filial;
use App\Rules\Cnpj;
use App\Support\Uf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FilialForm extends FormularioBase
{
    public ?Filial $filial = null;

    public string $nome = '';

    public string $codigo = '';

    public string $cnpj = '';

    public string $cidade = '';

    public string $uf = '';

    public string $telefone = '';

    public bool $ativo = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $ignorar = $this->filial?->getKey();

        return [
            'nome' => ['required', 'string', 'max:120', Rule::unique('filiais', 'nome')->ignore($ignorar)],
            'codigo' => ['required', 'string', 'max:10', Rule::unique('filiais', 'codigo')->ignore($ignorar)],
            'cnpj' => ['nullable', 'string', new Cnpj],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', Rule::in(Uf::siglas())],
            'telefone' => ['nullable', 'string', 'max:20'],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'nome' => 'nome',
            'codigo' => 'código',
            'cnpj' => 'CNPJ',
            'cidade' => 'cidade',
            'uf' => 'UF',
            'telefone' => 'telefone',
        ];
    }

    public function preencher(Filial $filial): void
    {
        $this->filial = $filial;
        $this->nome = $filial->nome;
        $this->codigo = $filial->codigo;
        $this->cnpj = (string) $filial->cnpj;
        $this->cidade = (string) $filial->cidade;
        $this->uf = (string) $filial->uf;
        $this->telefone = (string) $filial->telefone;
        $this->ativo = $filial->ativo;
    }

    public function criar(): Filial
    {
        $this->validate();

        return $this->executar(fn () => app(CriarFilial::class)->handle($this->dados(), Auth::user()));
    }

    public function atualizar(): Filial
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarFilial::class)->handle($this->filial, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'nome' => trim($this->nome),
            'codigo' => trim($this->codigo),
            'cnpj' => $this->cnpj !== '' ? $this->cnpj : null,
            'cidade' => $this->cidade !== '' ? trim($this->cidade) : null,
            'uf' => $this->uf !== '' ? $this->uf : null,
            'telefone' => $this->telefone !== '' ? trim($this->telefone) : null,
            'ativo' => $this->ativo,
        ];
    }
}
