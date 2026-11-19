<?php

namespace App\Actions\Fechamentos;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Desfaz o pagamento (a semana volta a "fechada"); necessário antes de reabrir uma semana paga. Exige motivo. */
class EstornarPagamento
{
    use AutorizaFechamento;

    public function handle(FechamentoSemanal $fechamento, User $usuario, string $motivo): FechamentoSemanal
    {
        $this->exigirPermissaoEFilial($usuario, 'fechamentos.pagar', $fechamento->filial_id);
        $motivo = $this->exigirMotivo($motivo);

        if ($fechamento->status !== FechamentoStatus::Pago) {
            throw ValidationException::withMessages(['semana' => 'Esta semana não está paga.']);
        }

        $pagoEm = $fechamento->pago_em;

        $fechamento->status = FechamentoStatus::Fechado;
        $fechamento->pago_por = null;
        $fechamento->pago_em = null;
        $fechamento->save();

        activity('fechamentos')
            ->performedOn($fechamento)->causedBy($usuario)
            ->withProperties(['motivo' => $motivo, 'pago_em_anterior' => $pagoEm?->toDateTimeString()])
            ->log('Pagamento estornado');

        return $fechamento;
    }
}
