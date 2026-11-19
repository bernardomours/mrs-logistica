<?php

namespace App\Actions\Motoristas;

use App\Models\Motorista;
use App\Models\User;
use App\Services\FilialAtual;
use App\Support\Texto;
use Illuminate\Validation\ValidationException;

class AtualizarMotorista
{
    public function __construct(private readonly FilialAtual $filiais) {}

    /**
     * Mudar de filial é permitido (RN-19); lançamentos antigos mantêm a filial original.
     *
     * @param  array<string, mixed>  $dados
     */
    public function handle(Motorista $motorista, array $dados): Motorista
    {
        if (! $this->filiais->podeAcessar((int) $dados['filial_id'])) {
            throw ValidationException::withMessages(['filial_id' => 'Você não tem acesso a esta filial.']);
        }

        $acesso = $motorista->user_id !== null ? User::query()->find($motorista->user_id) : null;

        if ($acesso !== null && isset($dados['cpf'])) {
            $cpf = Texto::digitos((string) $dados['cpf']);

            if (User::query()->where('cpf', $cpf)->whereKeyNot($acesso->id)->exists()) {
                throw ValidationException::withMessages(['cpf' => 'Já existe um usuário com este CPF.']);
            }
        }

        $motorista->update($dados);

        // O login do portal é o CPF do cadastro: mudou o CPF, muda o login (a senha continua a mesma).
        if ($acesso !== null) {
            $acesso->forceFill(['cpf' => $motorista->cpf, 'name' => $motorista->nome])->save();
        }

        return $motorista;
    }
}
