<?php

namespace App\Livewire\Forms;

use App\Actions\Manutencoes\AtualizarManutencao;
use App\Actions\Manutencoes\RegistrarManutencao;
use App\Enums\ManutencaoTipo;
use App\Models\Manutencao;
use App\Rules\NumeroBr;
use App\Support\Numero;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ManutencaoForm extends FormularioBase
{
    public ?Manutencao $manutencao = null;

    public string $veiculo_id = '';

    public string $motorista_id = '';

    public string $data_manutencao = '';

    public string $tipo = 'preventiva';

    public string $descricao = '';

    public string $oficina = '';

    public string $odometro_km = '';

    public string $valor_pecas = '';

    public string $valor_mao_obra = '';

    public string $nota_fiscal = '';

    public string $proxima_km = '';

    public string $proxima_data = '';

    public string $observacoes = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'veiculo_id' => ['required', Rule::exists('veiculos', 'id')],
            'motorista_id' => ['nullable', Rule::exists('motoristas', 'id')],
            'data_manutencao' => ['required', 'date'],
            'tipo' => ['required', Rule::enum(ManutencaoTipo::class)],
            'descricao' => ['required', 'string', 'max:2000'],
            'oficina' => ['nullable', 'string', 'max:150'],
            'odometro_km' => ['nullable', new NumeroBr(0, 9999999.9, 1)],
            'valor_pecas' => ['nullable', new NumeroBr(0, 9999999999.99)],
            'valor_mao_obra' => ['nullable', new NumeroBr(0, 9999999999.99)],
            'nota_fiscal' => ['nullable', 'string', 'max:50'],
            'proxima_km' => ['nullable', new NumeroBr(0, 9999999.9, 1)],
            'proxima_data' => ['nullable', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'veiculo_id' => 'veículo',
            'motorista_id' => 'motorista',
            'data_manutencao' => 'data da manutenção',
            'descricao' => 'descrição',
            'odometro_km' => 'hodômetro',
            'valor_pecas' => 'valor das peças',
            'valor_mao_obra' => 'valor da mão de obra',
            'nota_fiscal' => 'nota fiscal',
            'proxima_km' => 'km da próxima manutenção',
            'proxima_data' => 'data da próxima manutenção',
            'observacoes' => 'observações',
        ];
    }

    public function preencher(Manutencao $manutencao): void
    {
        $this->manutencao = $manutencao;
        $this->veiculo_id = (string) $manutencao->veiculo_id;
        $this->motorista_id = (string) $manutencao->motorista_id;
        $this->data_manutencao = $manutencao->data_manutencao->toDateString();
        $this->tipo = $manutencao->tipo->value;
        $this->descricao = $manutencao->descricao;
        $this->oficina = (string) $manutencao->oficina;
        $this->odometro_km = Numero::paraCampo($manutencao->odometro_km, 1);
        $this->valor_pecas = Numero::paraCampo($manutencao->valor_pecas);
        $this->valor_mao_obra = Numero::paraCampo($manutencao->valor_mao_obra);
        $this->nota_fiscal = (string) $manutencao->nota_fiscal;
        $this->proxima_km = Numero::paraCampo($manutencao->proxima_km, 1);
        $this->proxima_data = $manutencao->proxima_data?->toDateString() ?? '';
        $this->observacoes = (string) $manutencao->observacoes;
    }

    public function criar(): Manutencao
    {
        $this->validate();

        return $this->executar(fn () => app(RegistrarManutencao::class)->handle($this->dados(), Auth::user()));
    }

    public function atualizar(): Manutencao
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarManutencao::class)->handle($this->manutencao, $this->dados(), Auth::user()));
    }

    /** Total digitado (peças + mão de obra), só para exibir no formulário; a Action recalcula. */
    public function totalPrevisto(): string
    {
        return bcadd(Numero::paraDecimal($this->valor_pecas) ?? '0', Numero::paraDecimal($this->valor_mao_obra) ?? '0', 2);
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'veiculo_id' => (int) $this->veiculo_id,
            'motorista_id' => $this->motorista_id !== '' ? (int) $this->motorista_id : null,
            'data_manutencao' => $this->data_manutencao,
            'tipo' => $this->tipo,
            'descricao' => trim($this->descricao),
            'oficina' => $this->oficina !== '' ? trim($this->oficina) : null,
            'odometro_km' => Numero::paraDecimal($this->odometro_km, 1),
            'valor_pecas' => Numero::paraDecimal($this->valor_pecas) ?? '0.00',
            'valor_mao_obra' => Numero::paraDecimal($this->valor_mao_obra) ?? '0.00',
            'nota_fiscal' => $this->nota_fiscal !== '' ? trim($this->nota_fiscal) : null,
            'proxima_km' => Numero::paraDecimal($this->proxima_km, 1),
            'proxima_data' => $this->proxima_data !== '' ? $this->proxima_data : null,
            'observacoes' => $this->observacoes !== '' ? trim($this->observacoes) : null,
        ];
    }
}
