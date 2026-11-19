<?php

namespace App\Actions\Fechamentos;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** RN-11.4: remove o snapshot e destrava a semana. Só semana "fechada" (não paga) e sempre com motivo auditado. */
class ReabrirSemana
{
    use AutorizaFechamento;

    public function handle(FechamentoSemanal $fechamento, User $usuario, string $motivo): void
    {
        $this->exigirPermissaoEFilial($usuario, 'fechamentos.reabrir', $fechamento->filial_id);
        $motivo = $this->exigirMotivo($motivo);

        if ($fechamento->status === FechamentoStatus::Pago) {
            throw ValidationException::withMessages(['semana' => 'Semana paga: estorne o pagamento antes de reabrir.']);
        }

        DB::transaction(function () use ($fechamento, $usuario, $motivo) {
            activity('fechamentos')
                ->performedOn($fechamento)->causedBy($usuario)
                ->withProperties([
                    'motivo' => $motivo,
                    'motorista_id' => $fechamento->motorista_id,
                    'ano_iso' => $fechamento->ano_iso,
                    'semana_iso' => $fechamento->semana_iso,
                    'total_faturamento' => $fechamento->total_faturamento,
                    'total_comissao' => $fechamento->total_comissao,
                    'total_abastecimento' => $fechamento->total_abastecimento,
                ])
                ->log('Semana reaberta');

            $fechamento->delete();
        });
    }
}
