<?php

namespace App\Enums;

/** Situação de uma semana já fechada. Semana em aberto não tem registro (é calculada ao vivo). */
enum FechamentoStatus: string
{
    case Fechado = 'fechado';
    case Pago = 'pago';

    public function rotulo(): string
    {
        return match ($this) {
            self::Fechado => 'Fechada',
            self::Pago => 'Paga',
        };
    }

    /** Cor do selo na interface (ver `x-ui.badge`). */
    public function tipoDeBadge(): string
    {
        return match ($this) {
            self::Fechado => 'destaque',
            self::Pago => 'sucesso',
        };
    }
}
