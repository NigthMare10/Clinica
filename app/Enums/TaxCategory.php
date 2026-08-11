<?php

namespace App\Enums;

enum TaxCategory: string
{
    case EXENTO = 'EXENTO';
    case EXONERADO = 'EXONERADO';
    case GRAVADO_15 = 'GRAVADO_15';
    case GRAVADO_18 = 'GRAVADO_18';

    public function rate(): string
    {
        return match ($this) {
            self::GRAVADO_15 => '0.15',
            self::GRAVADO_18 => '0.18',
            self::EXENTO, self::EXONERADO => '0.00',
        };
    }
}
