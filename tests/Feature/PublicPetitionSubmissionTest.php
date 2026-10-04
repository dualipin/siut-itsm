<?php

use App\Actions\GeneratePetitionConsolidatedReport;
use App\Actions\GeneratePetitionVoucher;
use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function validCurp(): string
{
    return 'PEPM800101HDFRNS02';
}

function proposalPayload(array $proposals): array
{
    return [
        'curp' => validCurp(),
        'proposals' => $proposals,
    ];
}

test('a member can submit proposals and receives a voucher url', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $response = $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([
            ['proposal' => 'Solicito el aumento de aguinaldo.'],
            ['proposal' => 'Propongo una mesa de diálogo.', 'file' => UploadedFile::fake()->image('evidencia.jpg')],
        ])
    );

    $response->assertOk()
        ->assertJsonPath('message', 'Peticiones registradas correctamente.')
        ->assertJsonPath(
            'pdf_url',
            route('public.petitions.voucher', ['annual_petition' => $annualPetition->year, 'curp' => validCurp()])
        );

    expect(PetitionRequest::where('annual_petition_id', $annualPetition->id)->count())->toBe(2);

    $withAttachment = PetitionRequest::query()
        ->where('annual_petition_id', $annualPetition->id)
        ->where('proposal', 'like', '%mesa de diálogo%')
        ->firstOrFail();

    expect($withAttachment->curp)->toBe(validCurp())
        ->and($withAttachment->hasMedia('proposal_files'))->toBeTrue();
});

test('curp is normalized to upper case', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        [
            'curp' => strtolower(validCurp()),
            'proposals' => [['proposal' => 'Solicito capacitación continua.']],
        ]
    )->assertOk();

    expect(PetitionRequest::query()->firstOrFail()->curp)->toBe(validCurp());
});

test('a valid curp with a letter in the disambiguation position is accepted', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        [
            'curp' => 'sarm030413htcnyra3',
            'proposals' => [['proposal' => 'Solicito capacitación continua.']],
        ]
    )->assertOk();

    expect(PetitionRequest::query()->firstOrFail()->curp)->toBe('SARM030413HTCNYRA3');
});

test('the optional member name is trimmed and persisted on every proposal', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        [
            'curp' => validCurp(),
            'agremiado_name' => '  María Gómez López  ',
            'proposals' => [
                ['proposal' => 'Solicito capacitación continua.'],
                ['proposal' => 'Propongo más transparence.'],
            ],
        ]
    )->assertOk();

    expect(PetitionRequest::query()->pluck('agremiado_name')->unique()->all())
        ->toBe(['María Gómez López']);
});

test('the member name is optional and stored as null when omitted', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([['proposal' => 'Solicito capacitación continua.']])
    )->assertOk();

    expect(PetitionRequest::query()->firstOrFail()->agremiado_name)->toBeNull();
});

test('an overlong member name is rejected', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        [
            'curp' => validCurp(),
            'agremiado_name' => str_repeat('a', 256),
            'proposals' => [['proposal' => 'Solicito capacitación continua.']],
        ]
    )->assertStatus(422)
        ->assertJsonValidationErrors('agremiado_name');

    expect(PetitionRequest::query()->count())->toBe(0);
});

test('submission is rejected when the total exceeds four proposals for the same curp', function () {
    $annualPetition = AnnualPetition::factory()->create();

    PetitionRequest::factory()
        ->count(3)
        ->create([
            'annual_petition_id' => $annualPetition->id,
            'curp' => validCurp(),
        ]);

    $response = $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([
            ['proposal' => 'Primera propuesta adicional.'],
            ['proposal' => 'Segunda propuesta adicional.'],
        ])
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Se ha excedido el límite de 4 peticiones por agremiado para esta convocatoria.');

    expect(PetitionRequest::where('annual_petition_id', $annualPetition->id)->count())->toBe(3);
});

test('proposals from a different curp do not consume the limit of another curp', function () {
    $annualPetition = AnnualPetition::factory()->create();

    PetitionRequest::factory()
        ->count(4)
        ->create([
            'annual_petition_id' => $annualPetition->id,
            'curp' => validCurp(),
        ]);

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        [
            'curp' => 'PEPM800202HDFRNS01',
            'proposals' => [['proposal' => 'Petición de otro agremiado.']],
        ]
    )->assertOk();

    expect(PetitionRequest::where('annual_petition_id', $annualPetition->id)->count())->toBe(5);
});

test('submission is rejected after the deadline has passed', function () {
    $annualPetition = AnnualPetition::factory()->expired()->create();

    $response = $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([['proposal' => 'Propuesta fuera de tiempo.']])
    );

    $response->assertStatus(422)
        ->assertJsonPath('message', 'La fecha límite de esta convocatoria ya ha vencido.');

    expect(PetitionRequest::count())->toBe(0);
});

test('submission is still accepted later on the deadline date itself', function () {
    $annualPetition = AnnualPetition::factory()->create(['deadline' => today()]);

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([['proposal' => 'Propuesta registrada el día de la fecha límite.']])
    )->assertOk();

    expect(PetitionRequest::count())->toBe(1);
});

test('submission is rejected the day after the deadline', function () {
    $annualPetition = AnnualPetition::factory()->create(['deadline' => today()->subDay()]);

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        proposalPayload([['proposal' => 'Propuesta un día tarde.']])
    )->assertStatus(422)
        ->assertJsonPath('message', 'La fecha límite de esta convocatoria ya ha vencido.');

    expect(PetitionRequest::count())->toBe(0);
});

test('invalid payloads are rejected without persisting anything', function (array $payload, string $invalidField) {
    $annualPetition = AnnualPetition::factory()->create();

    $this->postJson(
        route('public.petitions.store', ['annualPetition' => $annualPetition->year]),
        $payload
    )->assertStatus(422)->assertJsonValidationErrors($invalidField);

    expect(PetitionRequest::count())->toBe(0);
})->with([
    'curp missing' => [
        ['proposals' => [['proposal' => 'Una propuesta válida.']]],
        'curp',
    ],
    'curp malformed' => [
        ['curp' => 'CURP-MAL-ERRONEA', 'proposals' => [['proposal' => 'Una propuesta válida.']]],
        'curp',
    ],
    'proposals empty' => [
        ['curp' => 'PEPM800101HDFRNS09', 'proposals' => []],
        'proposals',
    ],
    'proposal text too short' => [
        ['curp' => 'PEPM800101HDFRNS09', 'proposals' => [['proposal' => 'corto']]],
        'proposals.0.proposal',
    ],
    'unsupported attachment type' => [
        ['curp' => 'PEPM800101HDFRNS09', 'proposals' => [['proposal' => 'Una propuesta válida.', 'file' => UploadedFile::fake()->create('malware.exe', 10)]]],
        'proposals.0.file',
    ],
]);

test('the voucher can be downloaded for a registered curp', function () {
    $annualPetition = AnnualPetition::factory()->create();

    PetitionRequest::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'curp' => validCurp(),
        'proposal' => 'Solicito el aguinaldo correspondiente.',
    ]);

    $response = $this->get(route('public.petitions.voucher', [
        'annual_petition' => $annualPetition->year,
        'curp' => validCurp(),
    ]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the voucher returns 404 when the curp has no petitions', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->get(route('public.petitions.voucher', [
        'annual_petition' => $annualPetition->id,
        'curp' => validCurp(),
    ]))->assertNotFound();
});

test('the voucher pdf renders the member name', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $petitionRequest = PetitionRequest::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'curp' => validCurp(),
        'agremiado_name' => 'María Gómez López',
        'proposal' => 'Solicito el aguinaldo correspondiente.',
    ]);

    $html = view('pdf.petition-voucher', [
        'annualPetition' => $annualPetition,
        'petitionRequests' => collect([$petitionRequest]),
        'curp' => validCurp(),
        'primaryColor' => '#611232',
        'logoSrc' => public_path('assets/images/logo.webp'),
    ])->render();

    expect($html)->toContain('María Gómez López')
        ->and($html)->toContain('Nombre del Agremiado');

    expect((new GeneratePetitionVoucher)->execute($annualPetition, validCurp(), collect([$petitionRequest]))->output())
        ->toStartWith('%PDF');
});

test('the consolidated report pdf renders every member name', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $petitionRequest = PetitionRequest::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'agremiado_name' => 'María Gómez López',
        'proposal' => 'Solicito el aguinaldo correspondiente.',
    ]);

    $html = view('pdf.consolidated-report', [
        'annualPetition' => $annualPetition,
        'petitionRequests' => collect([$petitionRequest]),
        'primaryColor' => '#611232',
        'logoSrc' => public_path('assets/images/logo.webp'),
    ])->render();

    expect($html)->toContain('María Gómez López');

    expect((new GeneratePetitionConsolidatedReport)->execute($annualPetition)->output())
        ->toStartWith('%PDF');
});

test('deleting an annual petition cascades to its petition requests', function () {
    $annualPetition = AnnualPetition::factory()->create();

    PetitionRequest::factory()->count(2)->create([
        'annual_petition_id' => $annualPetition->id,
    ]);

    $annualPetition->delete();

    expect(PetitionRequest::count())->toBe(0);
});

test('the public petition page renders the annual petition data', function () {
    $annualPetition = AnnualPetition::factory()->create();

    $this->get(route('petitions.index', ['annualPetition' => $annualPetition->year]))
        ->assertOk()
        ->assertSee('petitions/petition-form', escape: false)
        ->assertSee((string) $annualPetition->year);
});
