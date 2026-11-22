<?php

use App\Services\IneValidationService;

beforeEach(function () {
    $this->service = new IneValidationService;
});

test('validateFront allows null AI payload for manual fallback', function () {
    expect($this->service->validateFront(null))->toBeNull();
});

test('validateFront accepts a valid current INE front', function () {
    $error = $this->service->validateFront([
        'is_valid_document' => true,
        'extracted_fields' => [
            'lado_detectado' => 'frente',
            'nombre' => 'CARLOS',
            'apellidos' => 'MENDOZA LOPEZ',
            'curp' => 'MELC950515HDFRRN09',
            'vigencia' => (string) (now()->year + 4),
        ],
        'observations' => '',
    ]);

    expect($error)->toBeNull();
});

test('validateFront rejects when reverso is uploaded in the front slot', function () {
    $error = $this->service->validateFront([
        'is_valid_document' => false,
        'extracted_fields' => [
            'lado_detectado' => 'reverso',
        ],
        'observations' => '',
    ]);

    expect($error)->not->toBeNull()
        ->and($error)->toContain('parte posterior');
});

test('validateFront rejects invalid document using AI observation', function () {
    $error = $this->service->validateFront([
        'is_valid_document' => false,
        'extracted_fields' => [
            'lado_detectado' => 'desconocido',
        ],
        'observations' => 'La imagen está demasiado borrosa para leer los datos.',
    ]);

    expect($error)->toBe('La imagen está demasiado borrosa para leer los datos.');
});

test('validateFront rejects expired INE front', function () {
    $expiredYear = (string) (now()->year - 1);

    $error = $this->service->validateFront([
        'is_valid_document' => true,
        'extracted_fields' => [
            'lado_detectado' => 'frente',
            'vigencia' => $expiredYear,
        ],
        'observations' => '',
    ]);

    expect($error)->not->toBeNull()
        ->and($error)->toContain($expiredYear);
});

test('validateBack allows null AI payload for manual fallback', function () {
    expect($this->service->validateBack(null))->toBeNull();
});

test('validateBack accepts valid INE back with MRZ', function () {
    $futureYear = now()->year + 4;
    $yy = substr((string) $futureYear, -2);

    $error = $this->service->validateBack([
        'is_valid_document' => true,
        'extracted_fields' => [
            'lado_detectado' => 'reverso',
            'mrz_linea_1' => 'IDMEX1234567890<<1234567890123',
            'mrz_linea_2' => "9505152M{$yy}12315MEX<02<<12345<6",
            'mrz_linea_3' => 'MENDOZA<LOPEZ<<CARLOS<<<<<<<<<',
            'vigencia_mrz' => (string) $futureYear,
        ],
        'observations' => '',
    ]);

    expect($error)->toBeNull();
});

test('validateBack rejects when front side is uploaded in the back slot', function () {
    $error = $this->service->validateBack([
        'is_valid_document' => false,
        'extracted_fields' => [
            'lado_detectado' => 'frente',
        ],
        'observations' => '',
    ]);

    expect($error)->not->toBeNull()
        ->and($error)->toContain('parte frontal');
});

test('validateBack rejects expired vigencia parsed from MRZ line 2', function () {
    $expiredYear = now()->year - 2;
    $yy = substr((string) $expiredYear, -2);

    $error = $this->service->validateBack([
        'is_valid_document' => true,
        'extracted_fields' => [
            'lado_detectado' => 'reverso',
            'mrz_linea_2' => "9505152M{$yy}12315MEX<02<<12345<6",
            'vigencia_mrz' => '',
        ],
        'observations' => '',
    ]);

    expect($error)->not->toBeNull()
        ->and($error)->toContain((string) $expiredYear);
});

test('validatePair rejects identical file hash even when AI data is missing', function () {
    $sameHash = hash('sha256', 'same-image-bytes');

    $error = $this->service->validatePair(
        ['hash' => $sameHash, 'ai' => null],
        ['hash' => $sameHash, 'ai' => null],
    );

    expect($error)->not->toBeNull()
        ->and($error)->toContain('idéntico');
});

test('validatePair accepts matching front and back with accented names and MRZ truncation', function () {
    $futureYear = (string) (now()->year + 4);
    $yy = substr($futureYear, -2);

    $error = $this->service->validatePair(
        [
            'hash' => hash('sha256', 'front-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre' => 'JOSÉ MARÍA',
                    'apellidos' => 'HERNÁNDEZ LÓPEZ',
                    'curp' => 'HELJ950515HDFRRN09',
                    'fecha_nacimiento' => '1995-05-15',
                    'vigencia' => $futureYear,
                ],
            ],
        ],
        [
            'hash' => hash('sha256', 'back-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'mrz_linea_2' => "9505152M{$yy}12315MEX<02<<12345<6",
                    'mrz_linea_3' => 'HERNANDEZ<LOPEZ<<JOSE<MAR<<<<<',
                    'nombre_mrz' => 'HERNANDEZ LOPEZ JOSE MAR',
                    'fecha_nacimiento_mrz' => '1995-05-15',
                    'vigencia_mrz' => $futureYear,
                ],
            ],
        ],
    );

    expect($error)->toBeNull();
});

test('validatePair tolerates older INE back without MRZ fields', function () {
    $error = $this->service->validatePair(
        [
            'hash' => hash('sha256', 'front-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre' => 'CARLOS',
                    'apellidos' => 'MENDOZA LOPEZ',
                    'curp' => 'MELC950515HDFRRN09',
                    'vigencia' => (string) (now()->year + 3),
                ],
            ],
        ],
        [
            'hash' => hash('sha256', 'back-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'lado_detectado' => 'reverso',
                    'mrz_linea_1' => '',
                    'mrz_linea_2' => '',
                    'mrz_linea_3' => '',
                    'nombre_mrz' => '',
                    'fecha_nacimiento_mrz' => '',
                    'vigencia_mrz' => '',
                ],
            ],
        ],
    );

    expect($error)->toBeNull();
});

test('validatePair rejects birth date mismatch between front and back', function () {
    $futureYear = (string) (now()->year + 4);

    $error = $this->service->validatePair(
        [
            'hash' => hash('sha256', 'front-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre' => 'CARLOS',
                    'apellidos' => 'MENDOZA LOPEZ',
                    'curp' => 'MELC950515HDFRRN09',
                    'fecha_nacimiento' => '1995-05-15',
                    'vigencia' => $futureYear,
                ],
            ],
        ],
        [
            'hash' => hash('sha256', 'back-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre_mrz' => 'MENDOZA LOPEZ CARLOS',
                    'fecha_nacimiento_mrz' => '1988-11-20',
                    'vigencia_mrz' => $futureYear,
                ],
            ],
        ],
    );

    expect($error)->not->toBeNull()
        ->and($error)->toContain('fecha de nacimiento');
});

test('validatePair rejects vigencia mismatch between front and back', function () {
    $frontYear = (string) (now()->year + 4);
    $backYear = (string) (now()->year + 6);

    $error = $this->service->validatePair(
        [
            'hash' => hash('sha256', 'front-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre' => 'CARLOS',
                    'apellidos' => 'MENDOZA LOPEZ',
                    'fecha_nacimiento' => '1995-05-15',
                    'vigencia' => $frontYear,
                ],
            ],
        ],
        [
            'hash' => hash('sha256', 'back-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre_mrz' => 'MENDOZA LOPEZ CARLOS',
                    'fecha_nacimiento_mrz' => '1995-05-15',
                    'vigencia_mrz' => $backYear,
                ],
            ],
        ],
    );

    expect($error)->not->toBeNull()
        ->and($error)->toContain('vigencia');
});

test('validatePair rejects name mismatch between front and back', function () {
    $futureYear = (string) (now()->year + 4);

    $error = $this->service->validatePair(
        [
            'hash' => hash('sha256', 'front-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre' => 'CARLOS',
                    'apellidos' => 'MENDOZA LOPEZ',
                    'fecha_nacimiento' => '1995-05-15',
                    'vigencia' => $futureYear,
                ],
            ],
        ],
        [
            'hash' => hash('sha256', 'back-bytes'),
            'ai' => [
                'is_valid_document' => true,
                'extracted_fields' => [
                    'nombre_mrz' => 'GARCIA RAMIREZ LAURA',
                    'fecha_nacimiento_mrz' => '1995-05-15',
                    'vigencia_mrz' => $futureYear,
                ],
            ],
        ],
    );

    expect($error)->not->toBeNull()
        ->and($error)->toContain('nombre');
});
