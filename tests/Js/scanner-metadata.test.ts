import test from 'node:test';
import assert from 'node:assert/strict';
import {
    MAX_PDF_BYTES,
    buildSuggestedPdfFilename,
    ensurePdfFilename,
    formatByteSize,
    validatePdfByteSize,
} from '../../resources/js/scanner/metadata.ts';

test('suggests PDF filename with priority Folio -> Nombre / Asunto -> timestamp', () => {
    const fixedNow = new Date(2026, 3, 5, 9, 7); // 2026-04-05 09:07 local

    assert.equal(
        buildSuggestedPdfFilename({
            referenceNumber: 'ITSM-SIUTITSM/031/04/2023',
            name: 'Oficio de Gestión',
            now: fixedNow,
        }),
        'ITSM-SIUTITSM-031-04-2023.pdf',
    );

    assert.equal(
        buildSuggestedPdfFilename({
            referenceNumber: '   ',
            name: 'Acta Ordinaria Enero 2025',
            now: fixedNow,
        }),
        'Acta-Ordinaria-Enero-2025.pdf',
    );

    assert.equal(
        buildSuggestedPdfFilename({
            referenceNumber: '',
            name: '   ',
            now: fixedNow,
        }),
        'escaneo-20260405-0907.pdf',
    );

    assert.equal(
        buildSuggestedPdfFilename({
            referenceNumber: '///',
            name: '---',
            now: fixedNow,
        }),
        'escaneo-20260405-0907.pdf',
    );
});

test('ensures edited filename always ends with .pdf and falls back when empty', () => {
    const fixedNow = new Date(2026, 10, 22, 21, 45);

    assert.equal(ensurePdfFilename('Balance_2025', { now: fixedNow }), 'Balance_2025.pdf');
    assert.equal(ensurePdfFilename('Acta Final.PDF', { now: fixedNow }), 'Acta-Final.pdf');
    assert.equal(ensurePdfFilename('  oficio/2025:01  ', { now: fixedNow }), 'oficio-2025-01.pdf');
    assert.equal(ensurePdfFilename('   ', { name: 'Respaldo', now: fixedNow }), 'Respaldo.pdf');
});

test('validates 15 MB byte limit strictly at the exact boundary', () => {
    assert.equal(MAX_PDF_BYTES, 15 * 1024 * 1024);

    assert.equal(validatePdfByteSize(0).valid, false);
    assert.equal(validatePdfByteSize(-10).valid, false);
    assert.equal(validatePdfByteSize(1024).valid, true);
    assert.equal(validatePdfByteSize(MAX_PDF_BYTES).valid, true);
    assert.equal(validatePdfByteSize(MAX_PDF_BYTES).exceedsLimit, false);

    const overLimit = validatePdfByteSize(MAX_PDF_BYTES + 1);
    assert.equal(overLimit.valid, false);
    assert.equal(overLimit.exceedsLimit, true);
    assert.equal(overLimit.maxBytes, MAX_PDF_BYTES);

    assert.equal(formatByteSize(512 * 1024), '512 KB');
    assert.equal(formatByteSize(15 * 1024 * 1024), '15.00 MB');
});
