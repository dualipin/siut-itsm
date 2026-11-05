<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Deterministic extraction of the fields of a Mexican payroll CFDI ("recibo de nómina")
 * from the text layer of a digital PDF. Exact and free, so it is tried before the AI.
 */
final class PayslipTextParser
{
    private const MONTHS = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
    ];

    private const DATE = '(\d{1,2}[\/\- ](?:[A-Za-z]{3}|\d{1,2})[\/\- ]\d{4})';

    /** Words that start the next label of the header block; they end a free-text value. */
    private const STOP = 'Depto|Departamento|Jornada|Tipo\s+salario|SBC|Domicilio|R[eé]gimen|NSS|Fecha|D[ií]as|Periodo|Ejercicio|Percepciones|RFC|CURP';

    /**
     * @return array<string, string> Empty strings for anything not found.
     */
    public static function parse(string $text): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        $curp = self::match($text, '/\b([A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z\d]\d)\b/');

        return [
            'nombre' => self::match($text, '/\b[A-Z]?\d{2,6}\s*-\s*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ .]+?)(?=\s+(?:RFC|CURP|NSS)\b|\s+\p{Lu}\p{Ll}|$)/u'),
            'numero_empleado' => self::match($text, '/\b([A-Z]?\d{2,6})\s*-\s*[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ .]+?(?=\s+(?:RFC|CURP|NSS)\b|\s+\p{Lu}\p{Ll}|$)/u'),
            'rfc' => self::match($text, '/\b([A-ZÑ&]{4}\d{6}[A-Z\d]{3})\b/u'),
            'curp' => $curp,
            'nss' => self::match($text, '/\bNSS\b\D{0,10}(\d{11})\b/i') ?: self::match($text, '/(?<!\d)(\d{11})(?!\d)/'),
            'categoria' => self::match($text, '/\bPuesto:?\s+(.+?)(?=\s+(?:'.self::STOP.')\b|$)/iu'),
            'departamento' => self::match($text, '/\bDepto\.?:?\s+(.+?)(?=\s+(?:'.self::STOP.'|Total)\b|$)/iu'),
            'salario' => self::money(self::match($text, '/Neto\s+del\s+recibo\s*\$?\s*([\d,]+\.\d{2})/i')),
            'periodo' => self::period($text),
            'fecha_pago' => self::date(self::match($text, '/Fecha\s+Pago:?\s*'.self::DATE.'/i')),
            'fecha_ingreso' => self::date(self::match($text, '/Fecha\s+Ini(?:cio)?\.?\s+Relaci[oó]n\s+Lab\.?:?\s*'.self::DATE.'/iu')),
            'fecha_nacimiento' => self::birthDateFromCurp($curp),
        ];
    }

    /**
     * Enough fields were read to trust the text layer without asking the AI.
     *
     * @param  array<string, string>  $fields
     */
    public static function isComplete(array $fields): bool
    {
        return $fields['nombre'] !== '' && $fields['nss'] !== '' && $fields['salario'] !== '' && $fields['curp'] !== '';
    }

    private static function match(string $text, string $pattern): string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : '';
    }

    private static function money(string $value): string
    {
        return str_replace(',', '', $value);
    }

    private static function period(string $text): string
    {
        $date = self::DATE;

        if (! preg_match("/Periodo:?\s*.*?{$date}\s*-\s*{$date}/i", $text, $m)) {
            return '';
        }

        $from = self::date($m[1]);
        $to = self::date($m[2]);

        return $from !== '' && $to !== ''
            ? CarbonImmutable::parse($from)->format('d/m/Y').' al '.CarbonImmutable::parse($to)->format('d/m/Y')
            : '';
    }

    /** Accepts "22/Oct/2026" (any Spanish 3-letter month) or "22/10/2026"; returns "YYYY-MM-DD" or "". */
    private static function date(string $value): string
    {
        if (! preg_match('/^(\d{1,2})[\/\- ]([A-Za-z]{3}|\d{1,2})[\/\- ](\d{4})$/', $value, $m)) {
            return '';
        }

        $month = ctype_digit($m[2]) ? (int) $m[2] : (self::MONTHS[strtolower($m[2])] ?? 0);

        return checkdate($month, (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $month, $m[1]) : '';
    }

    private static function birthDateFromCurp(string $curp): string
    {
        if ($curp === '') {
            return '';
        }

        $yy = (int) substr($curp, 4, 2);
        $mm = (int) substr($curp, 6, 2);
        $dd = (int) substr($curp, 8, 2);
        // Homoclave (17th char) is a digit for people born before 2000 and a letter from 2000 on.
        $year = ctype_alpha($curp[16]) ? 2000 + $yy : 1900 + $yy;

        return checkdate($mm, $dd, $year) ? sprintf('%04d-%02d-%02d', $year, $mm, $dd) : '';
    }
}
