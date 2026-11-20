<?php

namespace App\Actions\Usuarios;

use App\Models\User;
use App\Services\FilialAtual;
use App\Services\GestaoDeUsuarios;
use App\Support\PapeisDoSistema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Exclui definitivamente um usuário que nunca lançou nada no sistema (cadastro errado, por exemplo).
 * Quem já tem histórico (lançamentos, fechamentos, auditoria) só pode ser desativado.
 */
class ExcluirUsuario
{
    public function __construct(private readonly GestaoDeUsuarios $gestao, private readonly FilialAtual $filiais) {}

    public function handle(User $ator, User $alvo): void
    {
        if (! $ator->can('usuarios.excluir') || ! $this->gestao->podeGerenciar($ator, $alvo)) {
            throw new AuthorizationException('Você não tem permissão para excluir este usuário.');
        }

        if ($ator->is($alvo) && ! $ator->hasRole(PapeisDoSistema::ADMIN)) {
            throw ValidationException::withMessages(['usuario' => 'Você não pode excluir o seu próprio usuário.']);
        }

        if ($this->gestao->eOUltimoAdministradorAtivo($alvo)) {
            throw ValidationException::withMessages(['usuario' => 'Este é o único administrador ativo e não pode ser excluído.']);
        }

        if ($this->gestao->temHistorico($alvo)) {
            throw ValidationException::withMessages(['usuario' => 'Este usuário já tem histórico no sistema e não pode ser excluído. Desative-o.']);
        }

        DB::transaction(function () use ($ator, $alvo) {
            activity('usuarios')->performedOn($alvo)->causedBy($ator)
                ->withProperties(['nome' => $alvo->name, 'email' => $alvo->email])
                ->log('Usuário excluído');

            $this->gestao->encerrarSessoes($alvo);
            $alvo->syncRoles([]);
            $alvo->filiais()->detach();
            $alvo->delete();
            $this->filiais->esquecer();
        });
    }
}
