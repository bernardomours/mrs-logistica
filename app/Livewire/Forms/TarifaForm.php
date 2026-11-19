<?php

namespace App\Livewire\Forms;

use App\Actions\Tarifas\AtualizarTarifa;
use App\Actions\Tarifas\CriarTarifa;
use App\Models\TarifaRota;
use App\Rules\NumeroBr;
use App\Support\Numero;
use Illuminate\Validation\Rule;

class TarifaForm extends FormularioBase
{
    public ?TarifaRota $tarifa = null;

    public string $origem_local_id = '';

    public string $destino_local_id = '';

    public string $valor_frete = '';

    public string $vigencia_inicio = '';

    public string $vigencia_fim = '';

    public string $observacoes = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'origem_local_id' => ['required', Rule::exists('locais', 'id')],
            'destino_local_id' => ['required', Rule::exists('locais', 'id'), 'different:origem_local_id'],
            'valor_frete' => ['required', new NumeroBr(0.01, 9999999999.99)],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'origem_local_id' => 'origem',
            'destino_local_id' => 'destino',
            'valor_frete' => 'valor do frete',
            'vigencia_inicio' => 'início da vigência',
            'vigencia_fim' => 'fim da vigência',
            'observacoes' => 'observações',
        ];
    }

    public function preencher(TarifaRota $tarifa): void
    {
        $this->tarifa = $tarifa;
        $this->origem_local_id = (string) $tarifa->origem_local_id;
        $this->destino_local_id = (string) $tarifa->destino_local_id;
        $this->valor_frete = Numero::paraCampo($tarifa->valor_frete);
        $this->vigencia_inicio = $tarifa->vigencia_inicio->toDateString();
        $this->vigencia_fim = (string) $tarifa->vigencia_fim?->toDateString();
        $this->observacoes = (string) $tarifa->observacoes;
    }

    public function criar(): TarifaRota
    {
        $this->validate();

        return $this->executar(fn () => app(CriarTarifa::class)->handle($this->dados()));
    }

    public function atualizar(): TarifaRota
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarTarifa::class)->handle($this->tarifa, $this->dados()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            // Cliente não tem tela (decidido em 17/11/2026): tarifas pelo formulário valem para qualquer cliente.
            'cliente_id' => $this->tarifa?->cliente_id,
            'origem_local_id' => (int) $this->origem_local_id,
            'destino_local_id' => (int) $this->destino_local_id,
            'valor_frete' => Numero::paraDecimal($this->valor_frete),
            'vigencia_inicio' => $this->vigencia_inicio,
            'vigencia_fim' => $this->vigencia_fim !== '' ? $this->vigencia_fim : null,
            'observacoes' => $this->observacoes !== '' ? trim($this->observacoes) : null,
        ];
    }
}
