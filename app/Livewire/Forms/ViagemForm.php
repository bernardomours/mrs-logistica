<?php

namespace App\Livewire\Forms;

use App\Actions\Viagens\AtualizarViagem;
use App\Actions\Viagens\CriarViagem;
use App\Enums\ViagemStatus;
use App\Models\Viagem;
use App\Rules\NumeroBr;
use App\Support\Numero;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ViagemForm extends FormularioBase
{
    public ?Viagem $viagem = null;

    public string $motorista_id = '';

    public string $veiculo_id = '';

    public string $origem_local_id = '';

    public string $destino_local_id = '';

    public string $data_viagem = '';

    public string $valor_frete = '';

    public string $comissao_percentual = '';

    public string $status = 'concluida';

    public string $observacoes = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'motorista_id' => ['required', Rule::exists('motoristas', 'id')],
            'veiculo_id' => ['required', Rule::exists('veiculos', 'id')],
            'origem_local_id' => ['required', Rule::exists('locais', 'id')],
            'destino_local_id' => ['required', Rule::exists('locais', 'id'), 'different:origem_local_id'],
            'data_viagem' => ['required', 'date'],
            'valor_frete' => ['required', new NumeroBr(0.01, 9999999999.99)],
            'comissao_percentual' => ['nullable', new NumeroBr(0, 100)],
            'status' => ['required', Rule::in(array_map(fn (ViagemStatus $s) => $s->value, ViagemStatus::editaveis()))],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'motorista_id' => 'motorista',
            'veiculo_id' => 'veículo',
            'origem_local_id' => 'origem',
            'destino_local_id' => 'destino',
            'data_viagem' => 'data da viagem',
            'valor_frete' => 'valor do frete',
            'comissao_percentual' => 'porcentagem da comissão',
            'observacoes' => 'observações',
        ];
    }

    public function preencher(Viagem $viagem): void
    {
        $this->viagem = $viagem;
        $this->motorista_id = (string) $viagem->motorista_id;
        $this->veiculo_id = (string) $viagem->veiculo_id;
        $this->origem_local_id = (string) $viagem->origem_local_id;
        $this->destino_local_id = (string) $viagem->destino_local_id;
        $this->data_viagem = $viagem->data_viagem->toDateString();
        $this->valor_frete = Numero::paraCampo($viagem->valor_frete);
        $this->comissao_percentual = Numero::paraCampo($viagem->comissao_percentual);
        $this->status = $viagem->status->value;
        $this->observacoes = (string) $viagem->observacoes;
    }

    public function criar(): Viagem
    {
        $this->validate();

        return $this->executar(fn () => app(CriarViagem::class)->handle($this->dados(), Auth::user()));
    }

    public function atualizar(): Viagem
    {
        $this->validate();

        return $this->executar(fn () => app(AtualizarViagem::class)->handle($this->viagem, $this->dados(), Auth::user()));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return [
            'motorista_id' => (int) $this->motorista_id,
            'veiculo_id' => (int) $this->veiculo_id,
            'origem_local_id' => (int) $this->origem_local_id,
            'destino_local_id' => (int) $this->destino_local_id,
            'data_viagem' => $this->data_viagem,
            'valor_frete' => Numero::paraDecimal($this->valor_frete),
            'comissao_percentual' => Numero::paraDecimal($this->comissao_percentual),
            'status' => $this->status,
            'observacoes' => $this->observacoes !== '' ? trim($this->observacoes) : null,
        ];
    }
}
