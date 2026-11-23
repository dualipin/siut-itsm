<?php

use App\Enums\LoanGeneratedDocumentType;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanPdfGenerationService;
use App\Support\NumberToWords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->pdfService = app(LoanPdfGenerationService::class);
});

it('converts numbers to currency words in Spanish correctly', function () {
    expect(NumberToWords::toCurrency(10000.00))->toBe('(DIEZ MIL PESOS 00/100 M.N.)')
        ->and(NumberToWords::toCurrency(15250.50))->toBe('(QUINCE MIL DOSCIENTOS CINCUENTA PESOS 50/100 M.N.)')
        ->and(NumberToWords::toCurrency(1.00))->toBe('(UN PESO 00/100 M.N.)');
});

it('generates all 4 loan documents and saves them privately with versions and hashes', function () {
    $user = User::factory()->create([
        'name' => 'Juan Carlos',
        'surnames' => 'Bautista Gomez',
    ]);

    $loan = Loan::factory()->for($user)->create([
        'requested_amount' => 12000.00,
        'approved_amount' => 12000.00,
        'monthly_rate' => 6.00,
        'nomina_amount' => 12000.00,
        'nomina_fortnights' => 4,
        'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'signature_type' => 'drawn',
    ]);

    $admin = User::factory()->admin()->create();

    $result = $this->pdfService->generateAllDocuments($loan, $admin);

    expect($result['success'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty()
        ->and($loan->fresh()->documents_generated)->toBeTrue();

    // Check all 4 document records were created
    $generatedDocs = $loan->generatedDocuments;
    expect($generatedDocs)->toHaveCount(4);

    foreach ([
        LoanGeneratedDocumentType::Solicitud,
        LoanGeneratedDocumentType::Pagare,
        LoanGeneratedDocumentType::Anuencia,
        LoanGeneratedDocumentType::CorridaFinanciera,
    ] as $type) {
        $doc = $generatedDocs->firstWhere('document_type', $type);
        expect($doc)->not->toBeNull()
            ->and($doc->version)->toBe(1)
            ->and(strlen($doc->file_hash))->toBe(64)
            ->and(Storage::disk('local')->exists($doc->file_path))->toBeTrue();
    }

    // Test versioning by regenerating
    $this->pdfService->generateSingleDocument($loan, LoanGeneratedDocumentType::Solicitud, $admin);
    expect($loan->generatedDocuments()->where('document_type', LoanGeneratedDocumentType::Solicitud->value)->count())->toBe(2);
    expect($loan->generatedDocuments()->where('document_type', LoanGeneratedDocumentType::Solicitud->value)->max('version'))->toBe(2);
});

it('renders anuencia and solicitud using document.layout with union header and footer', function () {
    $user = User::factory()->create([
        'name' => 'Jesús Antonio',
        'surnames' => 'López Hernández',
    ]);

    $loan = Loan::factory()->for($user)->create([
        'requested_amount' => 2000.00,
        'approved_amount' => 2000.00,
        'monthly_rate' => 6.00,
        'nomina_amount' => 2000.00,
        'nomina_fortnights' => 4,
    ]);

    $viewData = [
        'loan' => $loan,
        'user' => $user,
        'amountInWords' => NumberToWords::toCurrency(2000.00),
        'approvalDateLong' => '08 de mayo de 2026',
        'lastPaymentDate' => '31/05/2026',
        'documentColors' => [
            'primary' => '#611232',
            'primary_content' => '#ffffff',
            'base_100' => '#f9fafb',
            'base_200' => '#f2f2f2',
            'base_300' => '#dddddd',
            'base_content' => '#000000',
        ],
        'logoSrc' => public_path('assets/images/logo.webp'),
        'installmentAmount' => 500.00,
        'firstFortnightDate' => '2026-05-31',
        'termFortnights' => 4,
    ];

    $anuenciaHtml = view('pdf.loans.anuencia', $viewData)->render();
    expect($anuenciaHtml)->toContain('SINDICATO ÚNICO DE TRABAJADORES')
        ->and($anuenciaHtml)->toContain('OST-SIUT-ITSM')
        ->and($anuenciaHtml)->toContain('ASUNTO: ANUENCIA DE DESCUENTO.')
        ->and($anuenciaHtml)->toContain('LIC. MANOLO GÓMEZ MORALES')
        ->and($anuenciaHtml)->toContain('AT\'N: LIC. EDUARDO ANTONIO CORNELIO MONTEJO.')
        ->and($anuenciaHtml)->toContain('APLICA LA TABLA:')
        ->and($anuenciaHtml)->toContain('CLÁUSULAS DE IRREVOCABILIDAD ABSOLUTA')
        ->and($anuenciaHtml)->toContain('page-break-before: always')
        ->and($anuenciaHtml)->toContain('Representación SIUTITSM')
        ->and($anuenciaHtml)->toContain('TESTIGO')
        ->and($anuenciaHtml)->toContain('C.C.P. Interesado.');

    $solicitudHtml = view('pdf.loans.solicitud', $viewData)->render();
    expect($solicitudHtml)->toContain('SINDICATO ÚNICO DE TRABAJADORES')
        ->and($solicitudHtml)->toContain('SOLICITUD DE PRÉSTAMO A LA CAJA DE AHORRO')
        ->and($solicitudHtml)->toContain('c.c.p Interesado');
});

it('generates pagare without embedded digital signature while preserving loan and debtor data on a single letter page', function () {
    $user = User::factory()->create([
        'name' => 'María Elena',
        'surnames' => 'García López',
        'address' => 'Av. Tecnológico 123, Macuspana, Tabasco',
        'phone' => '9361234567',
    ]);

    $loan = Loan::factory()->for($user)->submitted()->create([
        'folio' => 'SOL-2026-00999',
        'requested_amount' => 12000.00,
        'approved_amount' => 12000.00,
        'monthly_rate' => 6.00,
        'nomina_amount' => 12000.00,
        'nomina_fortnights' => 6,
        'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        'signature_type' => 'drawn',
    ]);

    $admin = User::factory()->admin()->create();

    $doc = $this->pdfService->generateSingleDocument($loan, LoanGeneratedDocumentType::Pagare, $admin);
    $pdfBytes = Storage::disk('local')->get($doc->file_path);

    $parsedPdf = (new Parser)->parseContent($pdfBytes);
    $pages = $parsedPdf->getPages();
    $pdfText = preg_replace('/\s+/', ' ', $parsedPdf->getText()) ?? '';

    $pagareHtml = view('pdf.loans.pagare', [
        'loan' => $loan,
        'user' => $user,
        'amountInWords' => NumberToWords::toCurrency(12000.00),
        'approvalDateLong' => '15 de mayo de 2026',
        'lastPaymentDate' => '15/08/2026',
        'fiscalYear' => 2026,
        'termFortnights' => 6,
    ])->render();

    expect($pages)->toHaveCount(1)
        ->and($parsedPdf->getObjectsByType('XObject', 'Image'))->toBeEmpty()
        ->and($pagareHtml)->not->toContain('<img')
        ->and($pagareHtml)->not->toContain($loan->signature_data)
        ->and($pdfText)->toContain('PAGARÉ')
        ->and($pdfText)->toContain('SOL-2026-00999')
        ->and($pdfText)->toContain('12,000.00')
        ->and($pdfText)->toContain('(DOCE MIL PESOS 00/100 M.N.)')
        ->and($pdfText)->toContain('Datos del deudor')
        ->and($pdfText)->toContain('Nombre: María Elena García López')
        ->and($pdfText)->toContain('Dirección: Av. Tecnológico 123, Macuspana, Tabasco')
        ->and($pdfText)->toContain('Teléfono: 9361234567')
        ->and($pdfText)->toContain('Acepto(amos)')
        ->and($pdfText)->toContain('Firma(s)');
});
