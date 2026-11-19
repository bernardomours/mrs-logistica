<?php

namespace App\Livewire\Forms;

use App\Actions\Veiculos\AtualizarVeiculo;
use App\Actions\Veiculos\CriarVeiculo;
use App\Enums\VeiculoTipo;
use App\Models\Veiculo;
use App\Rules\NumeroBr;
use App\Rules\Placa;
use App\Services\FilialAtual;
use App\Support\Numero;
use Illuminate\Validation\Rule;

class VeiculoForm extends FormularioBase
{
    public ?Veiculo $veiculo = null;

    public string $filial_id = '';

    public string $placa = '';

    public string $tipo = '';

    public string $marca = '';

    public string $modelo = '';

    public string $ano_fabricacao = '';

    public string $renavam = '';

    public string $capacidade_tanque_l = '';

    public string $odometro_inicial = '';

    public string $consumo_referencia_km_l = '';

    public string $motorista_padrao_id = '';

    public bool $ativo = true;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'filial_id' => ['required', Rule::exists('filiais', 'id')],
            'placa' => [
                'required', new Placa,
                fn (string $atributo, mixed $valor, \Closure $falhar) => Veiculo::withoutGlobalScopes()
                    ->where('placa', Placa::normalizar((string) $valor))
                    ->when($this->veiculo, fn ($q) => $q->whereKeyNot($this->veiculo->getKey()))
                    ->exists() ? $falhar('Já existe um veículo com esta placa.') : null,
            ],
            'tipo' => ['required', Rule::enum(VeiculoTipo::class)],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'ano_fabricacao' => ['nullable', 'integer', 'between:1950,'.((int) date('Y') + 1)],
            'renavam' => ['nullable', 'digits_between:9,11'],
            'capacidade_tanque_l' => ['nullable', new NumeroBr(1, 9999.99)],
            'odometro_inicial' => ['nullable', new NumeroBr(0, 9999999.9, 1)],
            'consumo_referencia_km_l' => ['nullable', new NumeroBr(0.1, 99.99)],
            'motorista_padrao_id' => ['nullable', Rule::exists('motoristas', 'id')],
            'ativo' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'filial_id' => 'filial',
            'ano_fabricacao' => 'ano de fabricação',
            'capacidade_tanque_l' => 'capacidade do tanque',
            'odometro_inicial' => 'hodômetro inicial',
            'consumo_referencia_km_l' => 'consumo de referência',
            'motorista_padrao_id' => 'motorista padrão',
        ];
    }

    public function preencher(Veiculo $veiculo): void
    {
        $this->veiculo = $veiculo;
        $this->filial_id = (string) $veiculo->filial_id;
        $this->placa = $veiculo->placa;
        $this->tipo = $veiculo->tipo->value;
        $this->marca = (string) $veiculo->marca;
        $this->modelo = (string) $veiculo->modelo;
        $this->ano_fabricacao = (string) $veiculo->ano_fabricacao;
        $this->renavam = (string) $veiculo->renavam;
        $this->capacidade_tanque_l = Numero::paraCampo($veiculo->capacidade_tanque_l);
        $this->odometro_inicial = Numero::paraCampo($veiculo->odometro_inicial, 1);
        $this->consumo_referencia_km_l = Numero::paraCampo($veiculo->consumo_referencia_km_l);
        $this->motorista_padrao_id = (string) $veiculo->motorista_padrao_id;
        $this->ativo = $veiculo->ativo;
    }

    public function sugerirFilial(): void
    {
        $this->filial_id = (string) (app(FilialAtual::class)->paraNovoLancamento() ?? '');
    }

    public function criar(): Veiculo
    {
        $this->validate();

        return $this->executar(fn () => app(CriarVeiculo::class)->handle($this->dados()));
    }

    public function atualizar(): Veiculo
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarVeiculo::class)->handle($this->veiculo, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'filial_id' => (int) $this->filial_id,
            'placa' => $this->placa,
            'tipo' => $this->tipo,
            'marca' => $this->marca !== '' ? trim($this->marca) : null,
            'modelo' => $this->modelo !== '' ? trim($this->modelo) : null,
            'ano_fabricacao' => $this->ano_fabricacao !== '' ? (int) $this->ano_fabricacao : null,
            'renavam' => $this->renavam !== '' ? $this->renavam : null,
            'capacidade_tanque_l' => Numero::paraDecimal($this->capacidade_tanque_l),
            'odometro_inicial' => Numero::paraDecimal($this->odometro_inicial, 1),
            'consumo_referencia_km_l' => Numero::paraDecimal($this->consumo_referencia_km_l),
            'motorista_padrao_id' => $this->motorista_padrao_id !== '' ? (int) $this->motorista_padrao_id : null,
            'ativo' => $this->ativo,
        ];
    }
}
