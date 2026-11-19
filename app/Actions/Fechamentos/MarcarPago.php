<?php

namespace App\Actions\Fechamentos;

use App\Enums\FechamentoStatus;
use App\Models\FechamentoSemanal;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** RN-11.3: registra o pagamento do `valor_a_pagar` ao motorista. */
class MarcarPago
{
    use AutorizaFechamento;

    public function handle(FechamentoSemanal $fechamento, User $usuario, ?CarbonInterface $pagoEm = null): FechamentoSemanal
    {
        $this->exigirPermissaoEFilial($usuario, 'fechamentos.pagar', $fechamento->filial_id);

        if ($fechamento->status !== FechamentoStatus::Fechado) {
            throw ValidationException::withMessages(['semana' => 'Só uma semana fechada e ainda não paga pode ser marcada como paga.']);
        }

        $fechamento->status = FechamentoStatus::Pago;
        $fechamento->pago_por = $usuario->id;
        $fechamento->pago_em = $pagoEm ?? now();
        $fechamento->save();

        return $fechamento;
    }
}
