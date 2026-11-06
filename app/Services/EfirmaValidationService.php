<?php

namespace App\Services;

use App\Exceptions\EfirmaValidationException;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Validates an SAT e.firma signature produced client-side: certificate trust chain,
 * validity, identity binding (CURP) and the RSA-SHA256 seal over the original chain.
 */
class EfirmaValidationService
{
    private const CHAIN_PATTERN = '/^\|\|SIUT-ITSM-PRESTAMO\|v1\|([a-f0-9]{64})\|(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\|\|$/';

    private const CURP_PATTERN = '/[A-Z]{4}\d{6}[HM][A-Z]{2}[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d/';

    private const RFC_PATTERN = '/[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}/u';

    private const MAX_CHAIN_DEPTH = 6;

    /** @var array<int, array{pem: string, parsed: array<string, mixed>}>|null */
    private ?array $trustedAuthorities = null;

    public static function buildOriginalChain(string $termsHash, CarbonImmutable $signedAt): string
    {
        return '||SIUT-ITSM-PRESTAMO|v1|'.$termsHash.'|'.$signedAt->utc()->format('Y-m-d\TH:i:s\Z').'||';
    }

    /**
     * @throws EfirmaValidationException
     */
    public function validate(
        User $user,
        string $certificateBase64,
        string $termsHash,
        string $originalChain,
        string $sealBase64,
    ): EfirmaValidationResult {
        $certificatePem = $this->toPem($certificateBase64);
        $parsed = openssl_x509_parse($certificatePem);

        if ($parsed === false) {
            throw new EfirmaValidationException('El certificado (.cer) no tiene un formato válido.');
        }

        $this->assertValidityPeriod($parsed);
        $identity = $this->extractIdentity($parsed);
        $this->assertIsEfirmaCertificate($parsed, $identity);
        $this->assertBelongsToUser($user, $identity['curp']);
        $this->assertTrustedChain($certificatePem, $parsed);
        $signedAt = $this->assertOriginalChain($originalChain, $termsHash);
        $this->assertSealIsValid($certificatePem, $originalChain, $sealBase64);

        return new EfirmaValidationResult(
            certificateBase64: base64_encode((string) base64_decode($certificateBase64, true)),
            certificateSerial: $this->extractSerial($parsed),
            rfc: $identity['rfc'],
            curp: $identity['curp'],
            signerName: $identity['name'],
            validFrom: CarbonImmutable::createFromTimestampUTC((int) $parsed['validFrom_time_t']),
            validTo: CarbonImmutable::createFromTimestampUTC((int) $parsed['validTo_time_t']),
            originalChain: $originalChain,
            seal: $sealBase64,
            signedAt: $signedAt,
        );
    }

    private function toPem(string $base64): string
    {
        $der = base64_decode(trim($base64), true);

        if ($der === false || $der === '') {
            throw new EfirmaValidationException('El certificado (.cer) no tiene un formato válido.');
        }

        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function assertValidityPeriod(array $parsed): void
    {
        $now = now()->timestamp;

        if ($now < (int) $parsed['validFrom_time_t']) {
            throw new EfirmaValidationException('El certificado de la e.firma aún no es vigente.');
        }

        if ($now > (int) $parsed['validTo_time_t']) {
            throw new EfirmaValidationException('El certificado de la e.firma está vencido.');
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array{rfc: string, curp: string, name: string}
     */
    private function extractIdentity(array $parsed): array
    {
        /** @var array<string, string|array<int, string>> $subject */
        $subject = $parsed['subject'] ?? [];

        $serialNumber = $this->subjectValue($subject, ['serialNumber', '2.5.4.5']);
        $uniqueIdentifier = $this->subjectValue($subject, ['x500UniqueIdentifier', '2.5.4.45']);
        $name = $this->subjectValue($subject, ['CN', 'commonName']);

        $curp = preg_match(self::CURP_PATTERN, strtoupper($serialNumber), $curpMatch) === 1 ? $curpMatch[0] : '';
        $rfc = preg_match(self::RFC_PATTERN, mb_strtoupper($uniqueIdentifier), $rfcMatch) === 1 ? $rfcMatch[0] : '';

        if ($curp === '' || $rfc === '') {
            throw new EfirmaValidationException('El certificado no contiene RFC y CURP de una persona física; no corresponde a una e.firma.');
        }

        return ['rfc' => $rfc, 'curp' => $curp, 'name' => $name];
    }

    /**
     * @param  array<string, string|array<int, string>>  $subject
     * @param  array<int, string>  $keys
     */
    private function subjectValue(array $subject, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($subject[$key])) {
                $value = $subject[$key];

                return trim(is_array($value) ? implode(' ', $value) : $value);
            }
        }

        return '';
    }

    /**
     * Rejects Certificados de Sello Digital: they carry an organizational unit
     * (sucursal) in the subject, and an e.firma must allow digital signatures.
     *
     * @param  array<string, mixed>  $parsed
     * @param  array{rfc: string, curp: string, name: string}  $identity
     */
    private function assertIsEfirmaCertificate(array $parsed, array $identity): void
    {
        /** @var array<string, mixed> $subject */
        $subject = $parsed['subject'] ?? [];

        if (isset($subject['OU']) || isset($subject['organizationalUnitName'])) {
            throw new EfirmaValidationException('El certificado corresponde a un sello digital (CSD), no a una e.firma.');
        }

        $keyUsage = (string) ($parsed['extensions']['keyUsage'] ?? '');

        if (! str_contains($keyUsage, 'Digital Signature')) {
            throw new EfirmaValidationException('El certificado no permite firma digital.');
        }
    }

    private function assertBelongsToUser(User $user, string $certificateCurp): void
    {
        if ($user->curp === null || ! hash_equals(strtoupper(trim($user->curp)), $certificateCurp)) {
            throw new EfirmaValidationException('El CURP del certificado no coincide con el del agremiado.');
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function assertTrustedChain(string $certificatePem, array $parsed): void
    {
        $authorities = $this->loadTrustedAuthorities();
        $currentPem = $certificatePem;
        $current = $parsed;
        $issuedAt = (int) $parsed['validFrom_time_t'];

        for ($depth = 0; $depth < self::MAX_CHAIN_DEPTH; $depth++) {
            $issuer = $this->findIssuer($current, $currentPem, $authorities);

            if ($issuer === null) {
                throw new EfirmaValidationException('El certificado no fue emitido por una Autoridad Certificadora reconocida del SAT.');
            }

            if ($issuedAt < (int) $issuer['parsed']['validFrom_time_t'] || $issuedAt > (int) $issuer['parsed']['validTo_time_t']) {
                throw new EfirmaValidationException('La Autoridad Certificadora emisora no era vigente al emitir el certificado.');
            }

            if ($issuer['parsed']['subject'] === $issuer['parsed']['issuer']) {
                return;
            }

            $currentPem = $issuer['pem'];
            $current = $issuer['parsed'];
        }

        throw new EfirmaValidationException('No fue posible completar la cadena de confianza del certificado.');
    }

    /**
     * @param  array<string, mixed>  $certificate
     * @param  array<int, array{pem: string, parsed: array<string, mixed>}>  $authorities
     * @return array{pem: string, parsed: array<string, mixed>}|null
     */
    private function findIssuer(array $certificate, string $certificatePem, array $authorities): ?array
    {
        foreach ($authorities as $authority) {
            if ($authority['parsed']['subject'] !== $certificate['issuer']) {
                continue;
            }

            if (openssl_x509_verify($certificatePem, $authority['pem']) === 1) {
                return $authority;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{pem: string, parsed: array<string, mixed>}>
     */
    private function loadTrustedAuthorities(): array
    {
        if ($this->trustedAuthorities !== null) {
            return $this->trustedAuthorities;
        }

        $this->trustedAuthorities = [];
        $files = glob(rtrim((string) config('efirma.ca_path'), '/\\').DIRECTORY_SEPARATOR.'*') ?: [];

        foreach ($files as $file) {
            if (! is_file($file) || ! in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['cer', 'crt', 'pem'], true)) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            $pem = str_contains($contents, 'BEGIN CERTIFICATE')
                ? $contents
                : "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($contents), 64, "\n")."-----END CERTIFICATE-----\n";

            $parsed = openssl_x509_parse($pem);

            if ($parsed !== false) {
                $this->trustedAuthorities[] = ['pem' => $pem, 'parsed' => $parsed];
            }
        }

        return $this->trustedAuthorities;
    }

    private function assertOriginalChain(string $originalChain, string $termsHash): CarbonImmutable
    {
        if (preg_match(self::CHAIN_PATTERN, $originalChain, $matches) !== 1) {
            throw new EfirmaValidationException('La cadena original de la firma no tiene el formato esperado.');
        }

        if (! hash_equals($termsHash, $matches[1])) {
            throw new EfirmaValidationException('La firma no corresponde a los términos actuales de la solicitud.');
        }

        $signedAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $matches[2], 'UTC');

        if ($signedAt === false || abs(now()->timestamp - $signedAt->timestamp) > (int) config('efirma.clock_tolerance')) {
            throw new EfirmaValidationException('La firma está fuera de la ventana de tiempo permitida. Verifica la hora de tu equipo y firma de nuevo.');
        }

        return $signedAt;
    }

    private function assertSealIsValid(string $certificatePem, string $originalChain, string $sealBase64): void
    {
        $seal = base64_decode($sealBase64, true);
        $publicKey = openssl_pkey_get_public($certificatePem);

        if ($seal === false || $seal === '' || $publicKey === false) {
            throw new EfirmaValidationException('El sello digital no tiene un formato válido.');
        }

        if (openssl_verify($originalChain, $seal, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new EfirmaValidationException('El sello digital no es válido para este certificado.');
        }
    }

    /**
     * SAT serial numbers are ASCII digits encoded as hex in the DER integer.
     *
     * @param  array<string, mixed>  $parsed
     */
    private function extractSerial(array $parsed): string
    {
        $hex = (string) ($parsed['serialNumberHex'] ?? '');
        $decoded = ctype_xdigit($hex) && strlen($hex) % 2 === 0 ? hex2bin($hex) : false;

        return $decoded !== false && ctype_digit($decoded) ? $decoded : $hex;
    }
}
