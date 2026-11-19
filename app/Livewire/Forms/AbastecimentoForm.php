<?php

namespace App\Livewire\Forms;

use App\Actions\Abastecimentos\AtualizarAbastecimento;
use App\Actions\Abastecimentos\CriarAbastecimento;
use App\Models\Abastecimento;
use App\Rules\NumeroBr;
use App\Support\Dinheiro;
use App\Support\Numero;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AbastecimentoForm extends FormularioBase
{
    public ?Abastecimento $abastecimento = null;

    public string $veiculo_id = '';

    public string $motorista_id = '';

    public string $posto_id = '';

    public string $data_abastecimento = '';

    public string $litros = '';

    public string $preco_litro = '';

    public string $valor_total = '';

    public string $odometro_km = '';

    /** Só é digitado no 1º abastecimento de um veículo sem hodômetro inicial; nos demais é automático (RN-07). */
    public string $km_anterior = '';

    public bool $tanque_cheio = true;

    public string $combustivel = 'diesel';

    public string $nota_fiscal = '';

    public string $observacoes = '';

    /**
     * Os dois campos do tripé (litros, preço, valor) digitados por último definem o terceiro (RN-06).
     *
     * @var array<int, string>
     */
    public array $editados = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'veiculo_id' => ['required', Rule::exists('veiculos', 'id')],
            'motorista_id' => ['required', Rule::exists('motoristas', 'id')],
            'posto_id' => ['nullable', Rule::exists('postos', 'id')],
            'data_abastecimento' => ['required', 'date'],
            'litros' => ['nullable', new NumeroBr(0.001, 99999.999, 3)],
            'preco_litro' => ['nullable', new NumeroBr(0.001, 999.999, 3)],
            'valor_total' => ['nullable', new NumeroBr(0.01, 9999999999.99)],
            'odometro_km' => ['required', new NumeroBr(0, 9999999.9, 1)],
            'km_anterior' => ['nullable', new NumeroBr(0, 9999999.9, 1)],
            'tanque_cheio' => ['boolean'],
            'combustivel' => ['required', Rule::in(array_keys(config('frota.combustiveis')))],
            'nota_fiscal' => ['nullable', 'string', 'max:50'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'veiculo_id' => 'veículo',
            'motorista_id' => 'motorista',
            'posto_id' => 'posto',
            'data_abastecimento' => 'data do abastecimento',
            'preco_litro' => 'preço por litro',
            'valor_total' => 'valor total',
            'odometro_km' => 'km final',
            'km_anterior' => 'km inicial',
            'nota_fiscal' => 'nota fiscal',
            'observacoes' => 'observações',
        ];
    }

    public function preencher(Abastecimento $abastecimento): void
    {
        $this->abastecimento = $abastecimento;
        $this->veiculo_id = (string) $abastecimento->veiculo_id;
        $this->motorista_id = (string) $abastecimento->motorista_id;
        $this->posto_id = (string) $abastecimento->posto_id;
        $this->data_abastecimento = $abastecimento->data_abastecimento->toDateString();
        $this->litros = Numero::paraCampo($abastecimento->litros, 3);
        $this->preco_litro = Numero::paraCampo($abastecimento->preco_litro, 3);
        $this->valor_total = Numero::paraCampo($abastecimento->valor_total);
        $this->odometro_km = Numero::paraCampo($abastecimento->odometro_km, 1);
        $this->km_anterior = Numero::paraCampo($abastecimento->km_anterior, 1);
        $this->tanque_cheio = $abastecimento->tanque_cheio;
        $this->combustivel = $abastecimento->combustivel;
        $this->nota_fiscal = (string) $abastecimento->nota_fiscal;
        $this->observacoes = (string) $abastecimento->observacoes;
        $this->editados = ['litros', 'preco_litro'];
    }

    /**
     * Chamado quando o usuário edita um campo do tripé: se os outros dois estão preenchidos,
     * recalcula o que NÃO foi digitado por último. Só dispara quando os dois valores são números válidos.
     */
    public function atualizarTripe(string $campo): void
    {
        $this->editados = array_values(array_slice([...array_diff($this->editados, [$campo]), $campo], -2));

        $alvo = collect(['litros', 'preco_litro', 'valor_total'])->first(fn ($c) => ! in_array($c, $this->editados, true));

        if ($alvo === null || count($this->editados) < 2) {
            return;
        }

        $v = [
            'litros' => Numero::paraDecimal($this->litros, 3),
            'preco_litro' => Numero::paraDecimal($this->preco_litro, 3),
            'valor_total' => Numero::paraDecimal($this->valor_total, 2),
        ];

        if (in_array(null, array_diff_key($v, [$alvo => 1]), true)
            || collect($v)->except($alvo)->contains(fn ($x) => Dinheiro::comparar($x, '0', 3) <= 0)) {
            return;
        }

        match ($alvo) {
            'valor_total' => $this->valor_total = Numero::paraCampo(Dinheiro::multiplicar($v['litros'], $v['preco_litro'], 2)),
            'litros' => $this->litros = Numero::paraCampo(Dinheiro::dividir($v['valor_total'], $v['preco_litro'], 3), 3),
            'preco_litro' => $this->preco_litro = Numero::paraCampo(Dinheiro::dividir($v['valor_total'], $v['litros'], 3), 3),
        };
    }

    public function criar(): Abastecimento
    {
        $this->validate();

        return $this->executar(fn () => app(CriarAbastecimento::class)->handle($this->dados(), Auth::user()));
    }

    public function atualizar(): Abastecimento
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarAbastecimento::class)->handle($this->abastecimento, $this->dados(), Auth::user()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'veiculo_id' => (int) $this->veiculo_id,
            'motorista_id' => (int) $this->motorista_id,
            'posto_id' => $this->posto_id !== '' ? (int) $this->posto_id : null,
            'data_abastecimento' => $this->data_abastecimento,
            'litros' => Numero::paraDecimal($this->litros, 3),
            'preco_litro' => Numero::paraDecimal($this->preco_litro, 3),
            'valor_total' => Numero::paraDecimal($this->valor_total, 2),
            'odometro_km' => Numero::paraDecimal($this->odometro_km, 1),
            'km_anterior' => Numero::paraDecimal($this->km_anterior, 1),
            'tanque_cheio' => $this->tanque_cheio,
            'combustivel' => $this->combustivel,
            'nota_fiscal' => $this->nota_fiscal !== '' ? trim($this->nota_fiscal) : null,
            'observacoes' => $this->observacoes !== '' ? trim($this->observacoes) : null,
        ];
    }
}
