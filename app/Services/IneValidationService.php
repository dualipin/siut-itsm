<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Deterministic validation for Mexican INE credentials (front, back, and front/back consistency).
 */
class IneValidationService
{
    /**
     * Validate the AI analysis payload for the front (anverso) of an INE.
     * Returns null when valid or when AI was unavailable; otherwise returns a user-facing error message.
     *
     * @param  array<string, mixed>|null  $aiData
     */
    public function validateFront(?array $aiData): ?string
    {
        if ($aiData === null) {
            return null;
        }

        $detectedSide = Str::lower(trim((string) data_get($aiData, 'extracted_fields.lado_detectado', '')));

        if ($detectedSide === 'reverso') {
            return 'Subiste la parte posterior (reverso) de tu INE en el campo frontal. Por favor sube el frente donde aparece tu fotografía, nombre y CURP.';
        }

        if (! ($aiData['is_valid_document'] ?? true)) {
            $observations = trim((string) ($aiData['observations'] ?? ''));

            return $observations !== ''
                ? $observations
                : 'El archivo no corresponde a la parte frontal válida de una credencial INE. Verifica que la imagen sea clara y legible.';
        }

        $vigencia = $this->extractFourDigitYear((string) data_get($aiData, 'extracted_fields.vigencia', ''));

        if ($vigencia !== null && $vigencia < now()->year) {
            return "La INE parece estar vencida (vigencia: {$vigencia}). Sube una credencial vigente.";
        }

        return null;
    }

    /**
     * Validate the AI analysis payload for the back (reverso) of an INE.
     * Returns null when valid or when AI was unavailable; otherwise returns a user-facing error message.
     *
     * @param  array<string, mixed>|null  $aiData
     */
    public function validateBack(?array $aiData): ?string
    {
        if ($aiData === null) {
            return null;
        }

        $detectedSide = Str::lower(trim((string) data_get($aiData, 'extracted_fields.lado_detectado', '')));

        if ($detectedSide === 'frente') {
            return 'Subiste la parte frontal de tu INE en el campo posterior. Por favor sube el reverso donde aparecen los códigos y la zona de lectura (IDMEX).';
        }

        if (! ($aiData['is_valid_document'] ?? true)) {
            $observations = trim((string) ($aiData['observations'] ?? ''));

            return $observations !== ''
                ? $observations
                : 'El archivo no corresponde a la parte posterior (reverso) válida de una credencial INE. Verifica que la imagen sea clara y legible.';
        }

        $backVigencia = $this->extractBackVigenciaYear($aiData);

        if ($backVigencia !== null && $backVigencia < now()->year) {
            return "El reverso de la INE indica vigencia vencida ({$backVigencia}). Sube una credencial vigente.";
        }

        return null;
    }

    /**
     * Cross-validate a staged INE front entry against a staged INE back entry.
     * Detects duplicate uploads and front/back mismatches (birth date, vigencia, name).
     *
     * @param  array<string, mixed>|null  $frontEntry
     * @param  array<string, mixed>|null  $backEntry
     */
    public function validatePair(?array $frontEntry, ?array $backEntry): ?string
    {
        if ($frontEntry === null || $backEntry === null) {
            return null;
        }

        $frontHash = (string) ($frontEntry['hash'] ?? '');
        $backHash = (string) ($backEntry['hash'] ?? '');

        if ($frontHash !== '' && $backHash !== '' && hash_equals($frontHash, $backHash)) {
            return 'El archivo de la parte posterior es idéntico al de la parte frontal. Debes subir ambos lados de tu credencial INE por separado.';
        }

        $frontAi = is_array($frontEntry['ai'] ?? null) ? $frontEntry['ai'] : null;
        $backAi = is_array($backEntry['ai'] ?? null) ? $backEntry['ai'] : null;

        if ($frontAi === null || $backAi === null) {
            return null;
        }

        // 1. Birth date consistency (Front fecha_nacimiento / CURP vs Back MRZ)
        $frontDobYmd = $this->extractFrontDobYmd($frontAi);
        $backDobYmd = $this->extractBackDobYmd($backAi);

        if ($frontDobYmd !== null && $backDobYmd !== null && $frontDobYmd !== $backDobYmd) {
            return 'La parte frontal y posterior de la INE no coinciden (la fecha de nacimiento del reverso no corresponde con la del frente). Verifica que ambos lados pertenezcan a la misma credencial.';
        }

        // 2. Vigencia consistency (Front vigencia vs Back MRZ vigencia)
        $frontVigencia = $this->extractFourDigitYear((string) data_get($frontAi, 'extracted_fields.vigencia', ''));
        $backVigencia = $this->extractBackVigenciaYear($backAi);

        if ($frontVigencia !== null && $backVigencia !== null && $frontVigencia !== $backVigencia) {
            return "La vigencia del reverso ({$backVigencia}) no coincide con la del frente ({$frontVigencia}). Verifica que ambos lados pertenezcan a la misma credencial.";
        }

        // 3. Name token overlap (Front nombre + apellidos vs Back MRZ line 3 / nombre_mrz)
        $frontTokens = $this->extractNameTokens(
            trim((string) data_get($frontAi, 'extracted_fields.nombre', '').' '.(string) data_get($frontAi, 'extracted_fields.apellidos', ''))
        );
        $backNameRaw = trim((string) data_get($backAi, 'extracted_fields.nombre_mrz', ''));

        if ($backNameRaw === '') {
            $backNameRaw = str_replace('<', ' ', (string) data_get($backAi, 'extracted_fields.mrz_linea_3', ''));
        }

        $backTokens = $this->extractNameTokens($backNameRaw);

        if (count($frontTokens) >= 2 && count($backTokens) >= 2 && ! $this->hasAnyTokenMatch($frontTokens, $backTokens)) {
            return 'El nombre en el reverso de la INE no coincide con el de la parte frontal. Verifica que ambos lados pertenezcan a la misma credencial.';
        }

        return null;
    }

    /**
     * Normalize DD/MM/YYYY or DD-MM-YYYY to ISO YYYY-MM-DD when possible.
     */
    public function normalizeDateString(string $raw): string
    {
        $trimmed = trim($raw);

        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $trimmed, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $trimmed;
    }

    /**
     * Extract a 6-digit YYMMDD birth date from the front INE payload (fecha_nacimiento or CURP).
     *
     * @param  array<string, mixed>  $frontAi
     */
    private function extractFrontDobYmd(array $frontAi): ?string
    {
        $rawDob = $this->normalizeDateString((string) data_get($frontAi, 'extracted_fields.fecha_nacimiento', ''));

        if ($rawDob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDob)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $rawDob)->format('ymd');
            } catch (\Exception) {
                // Fall through to CURP
            }
        }

        $curp = mb_strtoupper(trim((string) data_get($frontAi, 'extracted_fields.curp', '')));

        if (preg_match('/^[A-Z]{4}(\d{6})[HM][A-Z]{5}[A-Z\d]\d$/', $curp, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract a 6-digit YYMMDD birth date from the back INE payload (fecha_nacimiento_mrz or MRZ line 2).
     *
     * @param  array<string, mixed>  $backAi
     */
    private function extractBackDobYmd(array $backAi): ?string
    {
        $rawDob = $this->normalizeDateString((string) data_get($backAi, 'extracted_fields.fecha_nacimiento_mrz', ''));

        if ($rawDob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDob)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $rawDob)->format('ymd');
            } catch (\Exception) {
                // Fall through to MRZ line 2
            }
        }

        $mrzLine2 = preg_replace('/\s+/', '', mb_strtoupper((string) data_get($backAi, 'extracted_fields.mrz_linea_2', '')));

        if (is_string($mrzLine2) && preg_match('/^(\d{6})\d[MFH<]\d{6}/', $mrzLine2, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Extract the 4-digit expiry year (vigencia) from the back INE payload (vigencia_mrz or MRZ line 2).
     *
     * @param  array<string, mixed>  $backAi
     */
    private function extractBackVigenciaYear(array $backAi): ?int
    {
        $direct = $this->extractFourDigitYear((string) data_get($backAi, 'extracted_fields.vigencia_mrz', ''));

        if ($direct !== null) {
            return $direct;
        }

        $mrzLine2 = preg_replace('/\s+/', '', mb_strtoupper((string) data_get($backAi, 'extracted_fields.mrz_linea_2', '')));

        if (is_string($mrzLine2) && preg_match('/^\d{6}\d[MFH<](\d{2})\d{4}/', $mrzLine2, $m)) {
            return (int) ('20'.$m[1]);
        }

        return null;
    }

    private function extractFourDigitYear(string $raw): ?int
    {
        $trimmed = trim($raw);

        if (preg_match('/^\d{4}$/', $trimmed)) {
            return (int) $trimmed;
        }

        return null;
    }

    /**
     * Split a name string into uppercase ASCII alphabetic tokens of at least 2 characters.
     *
     * @return list<string>
     */
    private function extractNameTokens(string $raw): array
    {
        $normalized = Str::upper(Str::ascii($raw));
        $normalized = (string) preg_replace('/[^A-Z\s]/', ' ', $normalized);
        $parts = preg_split('/\s+/', trim($normalized)) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) >= 2) {
                $tokens[] = $part;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Check whether any name token matches exactly or as a prefix (accounting for 30-char MRZ truncation).
     *
     * @param  list<string>  $frontTokens
     * @param  list<string>  $backTokens
     */
    private function hasAnyTokenMatch(array $frontTokens, array $backTokens): bool
    {
        foreach ($frontTokens as $ft) {
            foreach ($backTokens as $bt) {
                if ($ft === $bt) {
                    return true;
                }

                if (strlen($ft) >= 3 && strlen($bt) >= 3 && (str_starts_with($ft, $bt) || str_starts_with($bt, $ft))) {
                    return true;
                }
            }
        }

        return false;
    }
}
