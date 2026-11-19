<?php

namespace App\Actions\Fechamentos;

use App\Models\User;
use App\Services\FilialAtual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Checagens comuns das Actions de fechamento. As Policies já escondem os botões; aqui é a defesa em
 * profundidade (as Actions recebem o usuário explicitamente e não dependem da sessão).
 */
trait AutorizaFechamento
{
    private function exigirPermissaoEFilial(User $usuario, string $permissao, int $filialId): void
    {
        if (! $usuario->can($permissao) || ! app(FilialAtual::class)->podeAcessar($filialId, $usuario)) {
            throw new AuthorizationException('Você não tem permissão para esta ação.');
        }
    }

    private function exigirMotivo(string $motivo): string
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo (pelo menos 5 caracteres).']);
        }

        return $motivo;
    }
}
