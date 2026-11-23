import test from 'node:test';
import assert from 'node:assert/strict';
import { buildPdfFromJpegPages, LETTER_WIDTH_PT } from '../../resources/js/scanner/pdf.ts';

function makeMinimalJpeg(bytes: number[] = [0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10, 0x4a, 0x46, 0x49, 0x46, 0x00, 0x01, 0xff, 0xd9]): Uint8Array {
    return Uint8Array.from(bytes);
}

test('builds a valid single-page PDF 1.4 with Carta width and proportional height', () => {
    const jpeg = makeMinimalJpeg();
    const pdf = buildPdfFromJpegPages([{ jpegBytes: jpeg, width: 1200, height: 1600 }]);
    const text = new TextDecoder('latin1').decode(pdf);

    assert.equal(LETTER_WIDTH_PT, 612);
    assert.ok(text.startsWith('%PDF-1.4\n'), 'PDF should start with %PDF-1.4 header');
    assert.ok(text.endsWith('%%EOF\n'), 'PDF should end with %%EOF');
    assert.match(text, /\/Type \/Catalog/);
    assert.match(text, /\/Type \/Pages[\s\S]*\/Count 1/);
    assert.match(text, /\/MediaBox \[0 0 612 816\]/);
    assert.match(text, /\/Subtype \/Image[\s\S]*\/Width 1200[\s\S]*\/Height 1600[\s\S]*\/Filter \/DCTDecode/);
    assert.match(text, new RegExp(`/Length ${jpeg.byteLength}\\b`));

    // Verify startxref points directly to xref
    const startXrefMatch = text.match(/startxref\n(\d+)\n%%EOF\n$/);
    assert.ok(startXrefMatch, 'startxref should be present at end of file');
    const xrefOffset = Number(startXrefMatch[1]);
    assert.equal(text.slice(xrefOffset, xrefOffset + 5), 'xref\n');

    // Verify every xref entry offset matches the exact object header byte offset
    const xrefSection = text.slice(xrefOffset);
    const entries = [...xrefSection.matchAll(/^(\d{10}) (\d{5}) ([fn]) $/gm)];
    assert.equal(entries.length, 6, '1 free entry + 5 objects for 1 page');
    assert.equal(entries[0][3], 'f');

    for (let objId = 1; objId <= 5; objId++) {
        const offset = Number(entries[objId][1]);
        assert.equal(entries[objId][3], 'n');
        assert.ok(
            text.slice(offset).startsWith(`${objId} 0 obj\n`),
            `Object ${objId} offset ${offset} should point to "${objId} 0 obj"`,
        );
    }
});

test('builds a multi-page PDF 1.4 with independent proportional MediaBox dimensions per page', () => {
    const page1 = makeMinimalJpeg([0xff, 0xd8, 0x01, 0x02, 0xff, 0xd9]);
    const page2 = makeMinimalJpeg([0xff, 0xd8, 0x03, 0x04, 0x05, 0xff, 0xd9]);
    const page3 = makeMinimalJpeg([0xff, 0xd8, 0x06, 0xff, 0xd9]);

    const pdf = buildPdfFromJpegPages([
        { jpegBytes: page1, width: 1200, height: 1600 },
        { jpegBytes: page2, width: 1600, height: 1200 },
        { jpegBytes: page3, width: 1000, height: 1000 },
    ]);

    const text = new TextDecoder('latin1').decode(pdf);

    assert.match(text, /\/Type \/Pages\s+\/Kids \[3 0 R 6 0 R 9 0 R\]\s+\/Count 3/);
    assert.match(text, /\/MediaBox \[0 0 612 816\]/);
    assert.match(text, /\/MediaBox \[0 0 612 459\]/);
    assert.match(text, /\/MediaBox \[0 0 612 612\]/);

    const startXrefMatch = text.match(/startxref\n(\d+)\n%%EOF\n$/);
    assert.ok(startXrefMatch);
    const xrefOffset = Number(startXrefMatch[1]);
    const entries = [...text.slice(xrefOffset).matchAll(/^(\d{10}) (\d{5}) ([fn]) $/gm)];
    assert.equal(entries.length, 12, '1 free entry + 11 objects for 3 pages');

    for (let objId = 1; objId <= 11; objId++) {
        const offset = Number(entries[objId][1]);
        assert.ok(text.slice(offset).startsWith(`${objId} 0 obj\n`));
    }
});

test('rejects empty page list or invalid page dimensions/bytes', () => {
    assert.throws(() => buildPdfFromJpegPages([]), /al menos una página/i);
    assert.throws(
        () => buildPdfFromJpegPages([{ jpegBytes: new Uint8Array(), width: 100, height: 100 }]),
        /vacía|bytes/i,
    );
    assert.throws(
        () => buildPdfFromJpegPages([{ jpegBytes: makeMinimalJpeg(), width: 0, height: 100 }]),
        /dimensiones/i,
    );
    assert.throws(
        () => buildPdfFromJpegPages([{ jpegBytes: makeMinimalJpeg(), width: 100, height: -5 }]),
        /dimensiones/i,
    );
});
