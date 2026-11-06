<?php

namespace App\Support;

class NumberToWords
{
    private const UNIDADES = [
        0 => '', 1 => 'UN', 2 => 'DOS', 3 => 'TRES', 4 => 'CUATRO',
        5 => 'CINCO', 6 => 'SEIS', 7 => 'SIETE', 8 => 'OCHO', 9 => 'NUEVE',
        10 => 'DIEZ', 11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE',
        15 => 'QUINCE', 16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO',
        19 => 'DIECINUEVE', 20 => 'VEINTE', 21 => 'VEINTIÚN', 22 => 'VEINTIDÓS',
        23 => 'VEINTITRÉS', 24 => 'VEINTICUATRO', 25 => 'VEINTICINCO',
        26 => 'VEINTISÉIS', 27 => 'VEINTISIETE', 28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
    ];

    private const DECENAS = [
        30 => 'TREINTA', 40 => 'CUARENTA', 50 => 'CINCUENTA', 60 => 'SESENTA',
        70 => 'SETENTA', 80 => 'OCHENTA', 90 => 'NOVENTA',
    ];

    private const CENTENAS = [
        100 => 'CIENTO', 200 => 'DOSCIENTOS', 300 => 'TRESCIENTOS',
        400 => 'CUATROCIENTOS', 500 => 'QUINIENTOS', 600 => 'SEISCIENTOS',
        700 => 'SETECIENTOS', 800 => 'OCHOCIENTOS', 900 => 'NOVECIENTOS',
    ];

    public static function toCurrency(float|int|string $number): string
    {
        $amount = (float) $number;
        $integers = (int) floor($amount);
        $decimals = (int) round(($amount - $integers) * 100);

        $words = self::convertNumber($integers);
        if ($words === '') {
            $words = 'CERO';
        }

        $pesoWord = ($integers === 1) ? 'PESO' : 'PESOS';
        $centsFormatted = sprintf('%02d/100 M.N.', $decimals);

        return "({$words} {$pesoWord} {$centsFormatted})";
    }

    public static function convertNumber(int $n): string
    {
        if ($n === 0) {
            return '';
        }

        if ($n < 30) {
            return self::UNIDADES[$n];
        }

        if ($n < 100) {
            $dec = (int) (floor($n / 10) * 10);
            $uni = $n % 10;

            return self::DECENAS[$dec].($uni > 0 ? ' Y '.self::UNIDADES[$uni] : '');
        }

        if ($n === 100) {
            return 'CIEN';
        }

        if ($n < 1000) {
            $cen = (int) (floor($n / 100) * 100);
            $rest = $n % 100;

            return self::CENTENAS[$cen].($rest > 0 ? ' '.self::convertNumber($rest) : '');
        }

        if ($n === 1000) {
            return 'MIL';
        }

        if ($n < 2000) {
            return 'MIL '.self::convertNumber($n % 1000);
        }

        if ($n < 1000000) {
            $miles = (int) floor($n / 1000);
            $rest = $n % 1000;

            return trim(self::convertNumber($miles).' MIL '.self::convertNumber($rest));
        }

        if ($n < 2000000) {
            return 'UN MILLÓN '.self::convertNumber($n % 1000000);
        }

        $millones = (int) floor($n / 1000000);
        $rest = $n % 1000000;

        return trim(self::convertNumber($millones).' MILLONES '.self::convertNumber($rest));
    }
}
