<?php

namespace App\Livewire\Forms;

use App\Actions\Motoristas\AtualizarMotorista;
use App\Actions\Motoristas\CriarMotorista;
use App\Models\Motorista;
use App\Rules\Cpf;
use App\Rules\NumeroBr;
use App\Services\FilialAtual;
use App\Support\Numero;
use App\Support\Texto;
use Illuminate\Validation\Rule;

class MotoristaForm extends FormularioBase
{
    public ?Motorista $motorista = null;

    public string $filial_id = '';

    public string $nome = '';

    public string $cpf = '';

    public string $cnh_numero = '';

    public string $cnh_categoria = '';

    public string $cnh_validade = '';

    public string $telefone = '';

    public string $comissao_percentual = '';

    public string $data_admissao = '';

    public bool $ativo = true;

    public string $observacoes = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'filial_id' => ['required', Rule::exists('filiais', 'id')],
            'nome' => ['required', 'string', 'max:150'],
            'cpf' => [
                'required', new Cpf,
                fn (string $atributo, mixed $valor, \Closure $falhar) => Motorista::withoutGlobalScopes()
                    ->where('cpf', Texto::digitos($valor))
                    ->when($this->motorista, fn ($q) => $q->whereKeyNot($this->motorista->getKey()))
                    ->exists() ? $falhar('Já existe um motorista com este CPF.') : null,
            ],
            'cnh_numero' => ['nullable', 'string', 'max:20'],
            'cnh_categoria' => ['nullable', Rule::in(['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'])],
            'cnh_validade' => ['nullable', 'date'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'comissao_percentual' => ['required', new NumeroBr(0, 100)],
            'data_admissao' => ['nullable', 'date'],
            'ativo' => ['boolean'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'filial_id' => 'filial',
            'cpf' => 'CPF',
            'cnh_numero' => 'número da CNH',
            'cnh_categoria' => 'categoria da CNH',
            'cnh_validade' => 'validade da CNH',
            'comissao_percentual' => 'porcentagem da comissão',
            'data_admissao' => 'data de admissão',
            'observacoes' => 'observações',
        ];
    }

    public function preencher(Motorista $motorista): void
    {
        $this->motorista = $motorista;
        $this->filial_id = (string) $motorista->filial_id;
        $this->nome = $motorista->nome;
        $this->cpf = $motorista->cpf;
        $this->cnh_numero = (string) $motorista->cnh_numero;
        $this->cnh_categoria = (string) $motorista->cnh_categoria;
        $this->cnh_validade = (string) $motorista->cnh_validade?->toDateString();
        $this->telefone = (string) $motorista->telefone;
        $this->comissao_percentual = Numero::paraCampo($motorista->comissao_percentual);
        $this->data_admissao = (string) $motorista->data_admissao?->toDateString();
        $this->ativo = $motorista->ativo;
        $this->observacoes = (string) $motorista->observacoes;
    }

    /** Pré-seleciona a filial para quem só acessa uma (ou tem uma filial padrão). */
    public function sugerirFilial(): void
    {
        $this->filial_id = (string) (app(FilialAtual::class)->paraNovoLancamento() ?? '');
    }

    public function criar(): Motorista
    {
        $this->validate();

        return $this->executar(fn () => app(CriarMotorista::class)->handle($this->dados()));
    }

    public function atualizar(): Motorista
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarMotorista::class)->handle($this->motorista, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'filial_id' => (int) $this->filial_id,
            'nome' => trim($this->nome),
            'cpf' => $this->cpf,
            'cnh_numero' => $this->cnh_numero !== '' ? trim($this->cnh_numero) : null,
            'cnh_categoria' => $this->cnh_categoria !== '' ? $this->cnh_categoria : null,
            'cnh_validade' => $this->cnh_validade !== '' ? $this->cnh_validade : null,
            'telefone' => $this->telefone !== '' ? trim($this->telefone) : null,
            'comissao_percentual' => Numero::paraDecimal($this->comissao_percentual),
            'data_admissao' => $this->data_admissao !== '' ? $this->data_admissao : null,
            'ativo' => $this->ativo,
            'observacoes' => $this->observacoes !== '' ? trim($this->observacoes) : null,
        ];
    }
}
