<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\FilialAtual;
use App\Services\GestaoDeUsuarios;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Cria um usuário da equipe (login por e-mail). Motoristas ganham acesso pelo cadastro de motoristas.
 */
class CriarUsuario
{
    public function __construct(private readonly GestaoDeUsuarios $gestao, private readonly FilialAtual $filiais) {}

    /**
     * @param  array{name: string, email: string, cpf: ?string, papel: string, filiais: array<int, int>, filial_padrao_id: ?int, ativo: bool, senha: string, deve_trocar_senha: bool}  $dados
     */
    public function handle(User $ator, array $dados): User
    {
        if (! $ator->can('usuarios.criar')) {
            throw new AuthorizationException('Você não tem permissão para criar usuários.');
        }

        $this->gestao->validarVinculos($ator, $dados['papel'], $dados['filiais'], $dados['filial_padrao_id']);

        return DB::transaction(function () use ($ator, $dados) {
            $usuario = new User([
                'name' => $dados['name'],
                'email' => mb_strtolower($dados['email']),
                'cpf' => $dados['cpf'],
                'password' => $dados['senha'],
                'ativo' => $dados['ativo'],
                'deve_trocar_senha' => $dados['deve_trocar_senha'],
                'filial_padrao_id' => $dados['filial_padrao_id'],
            ]);
            $usuario->save();

            $usuario->syncRoles([$dados['papel']]);
            $usuario->filiais()->sync($dados['filiais']);
            $this->filiais->esquecer();

            activity('usuarios')->performedOn($usuario)->causedBy($ator)
                ->withProperties(['papel' => $dados['papel'], 'filiais' => $dados['filiais'], 'ativo' => $dados['ativo']])
                ->log('Usuário criado');

            return $usuario;
        });
    }
}
