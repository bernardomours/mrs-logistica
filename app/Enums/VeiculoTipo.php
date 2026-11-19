<?php

namespace App\Enums;

enum VeiculoTipo: string
{
    case Cavalo = 'cavalo';
    case Truck = 'truck';
    case Toco = 'toco';
    case Carreta = 'carreta';
    case Utilitario = 'utilitario';

    public function rotulo(): string
    {
        return match ($this) {
            self::Cavalo => 'Cavalo mecânico',
            self::Truck => 'Truck',
            self::Toco => 'Toco',
            self::Carreta => 'Carreta',
            self::Utilitario => 'Utilitário',
        };
    }
}
