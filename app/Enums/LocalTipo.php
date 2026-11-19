<?php

namespace App\Enums;

enum LocalTipo: string
{
    case Cliente = 'cliente';
    case Fazenda = 'fazenda';
    case Crossdocking = 'crossdocking';
    case Cidade = 'cidade';
    case Base = 'base';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Fazenda => 'Fazenda',
            self::Crossdocking => 'Crossdocking',
            self::Cidade => 'Cidade',
            self::Base => 'Base',
            self::Outro => 'Outro',
        };
    }
}
