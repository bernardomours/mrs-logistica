<?php

namespace App\Actions\Veiculos;

use App\Models\Veiculo;
use App\Services\FilialAtual;

class AtualizarVeiculo
{
    public function __construct(private readonly FilialAtual $filiais) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(Veiculo $veiculo, array $dados): Veiculo
    {
        CriarVeiculo::validarFilialEMotorista($this->filiais, $dados);

        $veiculo->update($dados);

        return $veiculo;
    }
}
