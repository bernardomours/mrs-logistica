<?php

namespace App\Actions\Motoristas;

use App\Models\Motorista;
use App\Models\User;
use App\Services\FilialAtual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Esqueceu a senha: volta para o CPF (senha inicial). A senha nunca é registrada na auditoria. */
class RedefinirSenhaDoMotorista
{
    public function __construct(private readonly FilialAtual $filiais) {}

    public function handle(Motorista $motorista, User $usuario): void
    {
        if (! $usuario->can('usuarios.editar') || ! $this->filiais->podeAcessar($motorista->filial_id, $usuario)) {
            throw new AuthorizationException('Você não tem permissão para redefinir senhas.');
        }

        $acesso = $motorista->user_id !== null ? User::query()->find($motorista->user_id) : null;

        if ($acesso === null) {
            throw ValidationException::withMessages(['acesso' => 'Este motorista ainda não tem acesso.']);
        }

        $acesso->forceFill([
            'password' => $motorista->cpf,
            'deve_trocar_senha' => (bool) config('frota.portal_forcar_troca_senha'),
        ])->save();

        activity('motoristas')->performedOn($motorista)->causedBy($usuario)->log('Senha do portal redefinida para o CPF');
    }
}
