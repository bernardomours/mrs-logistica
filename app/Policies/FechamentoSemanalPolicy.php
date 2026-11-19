<?php

namespace App\Policies;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\Motorista;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Fechamento semanal (RN-11). `viewAny`/`view` seguem `fechamentos.ver`; as demais habilidades têm permissão própria
 * e respeitam o estado da semana. Fechamento não se edita nem se exclui direto: só reabre.
 */
class FechamentoSemanalPolicy extends CadastroPolicy
{
    protected function recurso(): string
    {
        return 'fechamentos';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $modelo): bool
    {
        return false;
    }

    /** Fechar uma semana em aberto: `Gate::authorize('fechar', [FechamentoSemanal::class, $motorista])`. */
    public function fechar(User $user, Motorista $motorista): bool
    {
        return $user->can('fechamentos.fechar') && $this->acessa($motorista);
    }

    public function pagar(User $user, FechamentoSemanal $fechamento): bool
    {
        return $user->can('fechamentos.pagar') && $this->acessa($fechamento) && $fechamento->status === FechamentoStatus::Fechado;
    }

    public function estornar(User $user, FechamentoSemanal $fechamento): bool
    {
        return $user->can('fechamentos.pagar') && $this->acessa($fechamento) && $fechamento->status === FechamentoStatus::Pago;
    }

    /** Só semana "fechada"; para reabrir uma paga, primeiro estorna-se o pagamento. */
    public function reabrir(User $user, FechamentoSemanal $fechamento): bool
    {
        return $user->can('fechamentos.reabrir') && $this->acessa($fechamento) && $fechamento->status === FechamentoStatus::Fechado;
    }

    /** Exportar a folha: precisa dos valores financeiros (RN-15) e da permissão de exportação. */
    public function exportar(User $user, Motorista $motorista): bool
    {
        return $user->can('fechamentos.ver') && $user->can('relatorios.exportar')
            && $user->can('viagens.ver_valores') && $user->can('comissoes.ver') && $this->acessa($motorista);
    }

    /** PDF de todos os motoristas da semana: mesmas permissões da exportação individual; o escopo de filial limita a lista. */
    public function exportarTodos(User $user): bool
    {
        return $user->can('fechamentos.ver') && $user->can('relatorios.exportar')
            && $user->can('viagens.ver_valores') && $user->can('comissoes.ver');
    }
}
