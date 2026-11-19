<?php

namespace App\Actions\Motoristas;

use App\Models\Motorista;
use App\Services\FilialAtual;
use Illuminate\Validation\ValidationException;

class CriarMotorista
{
    public function __construct(private readonly FilialAtual $filiais) {}

    /**
     * @param  array<string, mixed>  $dados
     */
    public function handle(array $dados): Motorista
    {
        if (! $this->filiais->podeAcessar((int) $dados['filial_id'])) {
            throw ValidationException::withMessages(['filial_id' => 'Você não tem acesso a esta filial.']);
        }

        return Motorista::create($dados);
    }
}
