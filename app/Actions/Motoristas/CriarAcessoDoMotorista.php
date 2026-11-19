<?php

namespace App\Actions\Motoristas;

use App\Models\Motorista;
use App\Models\User;
use App\Services\FilialAtual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RN-21: dá ao motorista um login no portal (somente leitura). Login = CPF e senha inicial = CPF; o motorista
 * pode trocar a senha depois (e, se `frota.portal_forcar_troca_senha` estiver ligado, é obrigado no 1º acesso).
 */
class CriarAcessoDoMotorista
{
    public function __construct(private readonly FilialAtual $filiais) {}

    public function handle(Motorista $motorista, User $usuario): User
    {
        if (! $usuario->can('usuarios.criar') || ! $this->filiais->podeAcessar($motorista->filial_id, $usuario)) {
            throw new AuthorizationException('Você não tem permissão para criar acessos.');
        }

        if ($motorista->user_id !== null) {
            throw ValidationException::withMessages(['acesso' => 'Este motorista já tem acesso.']);
        }

        if (! $motorista->ativo) {
            throw ValidationException::withMessages(['acesso' => 'Ative o motorista antes de criar o acesso.']);
        }

        if (strlen($motorista->cpf) !== 11) {
            throw ValidationException::withMessages(['acesso' => 'O CPF do motorista precisa ter 11 dígitos.']);
        }

        if (User::query()->where('cpf', $motorista->cpf)->exists()) {
            throw ValidationException::withMessages(['acesso' => 'Já existe um usuário com este CPF.']);
        }

        return DB::transaction(function () use ($motorista, $usuario) {
            $acesso = new User([
                'name' => $motorista->nome,
                'email' => "motorista.{$motorista->cpf}@acesso.mrslogistica.local",
                'cpf' => $motorista->cpf,
                'password' => $motorista->cpf,
                'ativo' => true,
                'deve_trocar_senha' => (bool) config('frota.portal_forcar_troca_senha'),
            ]);
            $acesso->save();
            $acesso->assignRole('motorista');

            $motorista->user_id = $acesso->id;
            $motorista->saveQuietly();

            activity('motoristas')->performedOn($motorista)->causedBy($usuario)->log('Acesso ao portal criado');

            return $acesso;
        });
    }
}
