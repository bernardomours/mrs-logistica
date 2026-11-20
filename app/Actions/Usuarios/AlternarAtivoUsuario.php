<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\GestaoDeUsuarios;
use App\Support\PapeisDoSistema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Ativa ou desativa um usuário. Desativado não entra e perde as sessões abertas. */
class AlternarAtivoUsuario
{
    public function __construct(private readonly GestaoDeUsuarios $gestao) {}

    public function handle(User $ator, User $alvo): User
    {
        if (! $ator->can('usuarios.desativar') || ! $this->gestao->podeGerenciar($ator, $alvo)) {
            throw new AuthorizationException('Você não tem permissão para ativar ou desativar este usuário.');
        }

        if ($ator->is($alvo) && ! $ator->hasRole(PapeisDoSistema::ADMIN)) {
            throw ValidationException::withMessages(['usuario' => 'Você não pode desativar o seu próprio usuário.']);
        }

        if ($alvo->ativo && $this->gestao->eOUltimoAdministradorAtivo($alvo)) {
            throw ValidationException::withMessages(['usuario' => 'Este é o único administrador ativo e não pode ser desativado.']);
        }

        $alvo->ativo = ! $alvo->ativo;
        $alvo->save();

        if (! $alvo->ativo) {
            $this->gestao->encerrarSessoes($alvo);
        }

        activity('usuarios')->performedOn($alvo)->causedBy($ator)->log($alvo->ativo ? 'Usuário reativado' : 'Usuário desativado');

        return $alvo;
    }
}
