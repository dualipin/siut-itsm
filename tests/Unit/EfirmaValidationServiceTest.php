<?php

use App\Exceptions\EfirmaValidationException;
use App\Models\User;
use App\Services\EfirmaValidationService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

const EFIRMA_TEST_CURP = 'HEGG560427MVZRRL04';
const EFIRMA_TEST_HASH = 'a3f1c0de5b6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f1a2b3c4d5e6f7a8b9c0d1e2f';

/**
 * @return array{config: string, dir: string}
 */
function efirmaOpenSslConfig(): array
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'efirma-test-'.bin2hex(random_bytes(4));
    mkdir($dir.DIRECTORY_SEPARATOR.'ca', 0777, true);

    $config = <<<'CNF'
[req]
distinguished_name = dn
prompt = no
[dn]
CN = placeholder
[v3_ca]
basicConstraints = critical,CA:TRUE
keyUsage = critical,keyCertSign,cRLSign
[v3_leaf]
basicConstraints = CA:FALSE
keyUsage = digitalSignature,nonRepudiation
[v3_leaf_no_signing]
basicConstraints = CA:FALSE
keyUsage = keyEncipherment
CNF;

    file_put_contents($dir.DIRECTORY_SEPARATOR.'openssl.cnf', $config);

    return ['config' => $dir.DIRECTORY_SEPARATOR.'openssl.cnf', 'dir' => $dir];
}

/**
 * Issues a certificate signed by $issuer (or self-signed when null).
 *
 * @param  array<string, string>  $dn
 * @param  array{cert: OpenSSLCertificate, key: OpenSSLAsymmetricKey}|null  $issuer
 * @return array{cert: OpenSSLCertificate, key: OpenSSLAsymmetricKey}
 */
function efirmaIssue(string $config, array $dn, string $extensions, ?array $issuer = null, int $days = 365, int $serial = 1): array
{
    $args = ['config' => $config, 'private_key_bits' => 2048, 'digest_alg' => 'sha256'];
    $key = openssl_pkey_new($args);
    $csr = openssl_csr_new($dn, $key, $args);
    $cert = openssl_csr_sign(
        $csr,
        $issuer['cert'] ?? null,
        $issuer['key'] ?? $key,
        $days,
        [...$args, 'x509_extensions' => $extensions],
        $serial,
    );

    return ['cert' => $cert, 'key' => $key];
}

/**
 * @param  array<string, string>  $leafDn
 * @return array{certificate: string, key: OpenSSLAsymmetricKey, dir: string, config: string}
 */
function efirmaFixture(array $leafDn = [], string $leafExtensions = 'v3_leaf', int $leafDays = 365): array
{
    ['config' => $config, 'dir' => $dir] = efirmaOpenSslConfig();

    $root = efirmaIssue($config, ['CN' => 'AC Test Raiz', 'O' => 'SAT Test'], 'v3_ca', null, 3650, 1);
    $intermediate = efirmaIssue($config, ['CN' => 'AC Test Intermedia', 'O' => 'SAT Test'], 'v3_ca', $root, 3000, 2);

    foreach (['root.cer' => $root, 'intermediate.cer' => $intermediate] as $name => $authority) {
        openssl_x509_export($authority['cert'], $pem);
        file_put_contents($dir.DIRECTORY_SEPARATOR.'ca'.DIRECTORY_SEPARATOR.$name, $pem);
    }

    $leaf = efirmaIssue($config, [
        'CN' => 'GABRIELA HERNANDEZ GARCIA',
        'serialNumber' => ' / '.EFIRMA_TEST_CURP,
        'x500UniqueIdentifier' => 'HEGG560427MV4 / HEGG560427MV4',
        ...$leafDn,
    ], $leafExtensions, $intermediate, $leafDays, 3);

    openssl_x509_export($leaf['cert'], $leafPem);
    $der = base64_decode(trim(preg_replace('/-----[A-Z ]+-----/', '', $leafPem)));

    config()->set('efirma.ca_path', $dir.DIRECTORY_SEPARATOR.'ca');

    return ['certificate' => base64_encode($der), 'key' => $leaf['key'], 'dir' => $dir, 'config' => $config];
}

function efirmaSign(OpenSSLAsymmetricKey $key, string $chain): string
{
    openssl_sign($chain, $signature, $key, OPENSSL_ALGO_SHA256);

    return base64_encode($signature);
}

function efirmaUser(?string $curp = EFIRMA_TEST_CURP): User
{
    return User::factory()->make(['curp' => $curp]);
}

it('validates a correct e.firma and extracts the signer identity', function () {
    $fixture = efirmaFixture();
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    $result = (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );

    expect($result->curp)->toBe(EFIRMA_TEST_CURP)
        ->and($result->rfc)->toBe('HEGG560427MV4')
        ->and($result->signerName)->toBe('GABRIELA HERNANDEZ GARCIA')
        ->and($result->originalChain)->toBe($chain);
});

it('rejects an e.firma whose CURP differs from the member', function () {
    $fixture = efirmaFixture();
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser('AAAA010101HDFRRL09'),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'CURP');

it('rejects a certificate not issued by a trusted SAT authority', function () {
    $fixture = efirmaFixture();
    $emptyDir = $fixture['dir'].DIRECTORY_SEPARATOR.'empty';
    mkdir($emptyDir);
    config()->set('efirma.ca_path', $emptyDir);
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'Autoridad Certificadora');

it('rejects an expired certificate', function () {
    $fixture = efirmaFixture(leafDays: 1);
    $this->travel(3)->days();
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'vencido');

it('rejects a CSD certificate identified by its organizational unit', function () {
    $fixture = efirmaFixture(['OU' => 'MATRIZ']);
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'CSD');

it('rejects a certificate without digital signature key usage', function () {
    $fixture = efirmaFixture(leafExtensions: 'v3_leaf_no_signing');
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'firma digital');

it('rejects a seal produced with a different private key', function () {
    $fixture = efirmaFixture();
    $otherKey = openssl_pkey_new(['config' => $fixture['config'], 'private_key_bits' => 2048]);
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($otherKey, $chain),
    );
})->throws(EfirmaValidationException::class, 'sello digital');

it('rejects a signature over terms different from the current ones', function () {
    $fixture = efirmaFixture();
    $chain = EfirmaValidationService::buildOriginalChain(str_repeat('b', 64), CarbonImmutable::now());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'términos actuales');

it('rejects a stale signature timestamp', function () {
    $fixture = efirmaFixture();
    $chain = EfirmaValidationService::buildOriginalChain(EFIRMA_TEST_HASH, CarbonImmutable::now()->subHour());

    (new EfirmaValidationService)->validate(
        efirmaUser(),
        $fixture['certificate'],
        EFIRMA_TEST_HASH,
        $chain,
        efirmaSign($fixture['key'], $chain),
    );
})->throws(EfirmaValidationException::class, 'ventana de tiempo');

it('rejects malformed certificate data', function () {
    (new EfirmaValidationService)->validate(efirmaUser(), 'not-a-cert!!', EFIRMA_TEST_HASH, 'x', 'y');
})->throws(EfirmaValidationException::class, 'formato');
