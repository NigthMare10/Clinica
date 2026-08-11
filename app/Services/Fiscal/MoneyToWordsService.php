<?php

namespace App\Services\Fiscal;

class MoneyToWordsService
{
    public function lempiras(string|int|float $amount): string
    {
        $cents = (int) round(((float) $amount) * 100);
        $whole = intdiv($cents, 100);
        $fraction = $cents % 100;
        $words = $this->number($whole);

        return $fraction === 0 ? "$words LEMPIRAS EXACTOS" : "$words LEMPIRAS CON ".str_pad((string) $fraction, 2, '0', STR_PAD_LEFT).'/100';
    }

    private function number(int $number): string
    {
        if ($number === 0) {
            return 'CERO';
        }
        if ($number < 1000) {
            return $this->underThousand($number);
        }
        if ($number < 1000000) {
            $thousands = intdiv($number, 1000);
            $remainder = $number % 1000;

            return ($thousands === 1 ? 'MIL' : $this->underThousand($thousands).' MIL').($remainder ? ' '.$this->underThousand($remainder) : '');
        }
        $millions = intdiv($number, 1000000);
        $remainder = $number % 1000000;

        return ($millions === 1 ? 'UN MILLÓN' : $this->number($millions).' MILLONES').($remainder ? ' '.$this->number($remainder) : '');
    }

    private function underThousand(int $number): string
    {
        $units = [0 => '', 1 => 'UNO', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO', 5 => 'CINCO', 6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE', 10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE', 16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE', 20 => 'VEINTE', 30 => 'TREINTA', 40 => 'CUARENTA', 50 => 'CINCUENTA', 60 => 'SESENTA', 70 => 'SETENTA', 80 => 'OCHENTA', 90 => 'NOVENTA'];
        if ($number < 21) {
            return $units[$number];
        }
        if ($number < 30) {
            return match ($number) {
                21 => 'VEINTIUNO', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE'
            };
        }
        if ($number < 100) {
            return $units[intdiv($number, 10) * 10].($number % 10 ? ' Y '.$units[$number % 10] : '');
        }
        if ($number === 100) {
            return 'CIEN';
        }
        $hundreds = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

        return $hundreds[intdiv($number, 100)].($number % 100 ? ' '.$this->underThousand($number % 100) : '');
    }
}
