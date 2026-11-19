<?php

namespace App\Livewire\Forms;

use App\Actions\Postos\AtualizarPosto;
use App\Actions\Postos\CriarPosto;
use App\Models\Posto;
use App\Rules\Cnpj;
use App\Support\Texto;
use App\Support\Uf;
use Illuminate\Validation\Rule;

class PostoForm extends FormularioBase
{
    public ?Posto $posto = null;

    public string $nome = '';

    public string $cnpj = '';

    public string $cidade = '';

    public string $uf = '';

    public bool $ativo = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:150',
                fn (string $atributo, mixed $valor, \Closure $falhar) => Posto::query()
                    ->where('nome_chave', Texto::chave((string) $valor))
                    ->when($this->posto, fn ($q) => $q->whereKeyNot($this->posto->getKey()))
                    ->exists() ? $falhar('Já existe um posto com este nome.') : null,
            ],
            'cnpj' => ['nullable', new Cnpj],
            'cidade' => ['nullable', 'string', 'max:100'],
            'uf' => ['nullable', Rule::in(Uf::siglas())],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['cnpj' => 'CNPJ', 'uf' => 'UF'];
    }

    public function preencher(Posto $posto): void
    {
        $this->posto = $posto;
        $this->nome = $posto->nome;
        $this->cnpj = (string) $posto->cnpj;
        $this->cidade = (string) $posto->cidade;
        $this->uf = (string) $posto->uf;
        $this->ativo = $posto->ativo;
    }

    public function criar(): Posto
    {
        $this->validate();

        return $this->executar(fn () => app(CriarPosto::class)->handle($this->dados()));
    }

    public function atualizar(): Posto
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarPosto::class)->handle($this->posto, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'nome' => trim($this->nome),
            'cnpj' => $this->cnpj !== '' ? $this->cnpj : null,
            'cidade' => $this->cidade !== '' ? trim($this->cidade) : null,
            'uf' => $this->uf !== '' ? $this->uf : null,
            'ativo' => $this->ativo,
        ];
    }
}
