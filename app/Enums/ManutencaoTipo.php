<?php

namespace App\Enums;

enum ManutencaoTipo: string
{
    case Preventiva = 'preventiva';
    case Corretiva = 'corretiva';
    case Pneus = 'pneus';
    case Revisao = 'revisao';
    case Outros = 'outros';

    public function rotulo(): string
    {
        return match ($this) {
            self::Preventiva => 'Preventiva',
            self::Corretiva => 'Corretiva',
            self::Pneus => 'Pneus',
            self::Revisao => 'Revisão',
            self::Outros => 'Outros',
        };
    }

    /** Cor do selo na interface (ver `x-ui.badge`). */
    public function tipoDeBadge(): string
    {
        return match ($this) {
            self::Preventiva, self::Revisao => 'sucesso',
            self::Corretiva => 'erro',
            self::Pneus => 'destaque',
            self::Outros => 'neutro',
        };
    }
}
