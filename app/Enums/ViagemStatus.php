<?php

namespace App\Enums;

enum ViagemStatus: string
{
    case Planejada = 'planejada';
    case EmTransito = 'em_transito';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Planejada => 'Planejada',
            self::EmTransito => 'Em trânsito',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Cor do selo na interface (ver `x-ui.badge`). */
    public function tipoDeBadge(): string
    {
        return match ($this) {
            self::Concluida => 'sucesso',
            self::EmTransito => 'destaque',
            self::Planejada => 'aviso',
            self::Cancelada => 'neutro',
        };
    }

    /** Só viagens concluídas entram nos totais financeiros (RN-03). */
    public function contaNosTotais(): bool
    {
        return $this === self::Concluida;
    }

    /** Status que o usuário pode escolher no formulário (cancelar é uma ação própria). */
    public static function editaveis(): array
    {
        return [self::Planejada, self::EmTransito, self::Concluida];
    }
}
