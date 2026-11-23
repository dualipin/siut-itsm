import test from 'node:test';
import assert from 'node:assert/strict';
import {
    DEFAULT_SCAN_FILTER,
    applyScanFilter,
    movePage,
    normalizeRotation,
    rotateImageData,
} from '../../resources/js/scanner/filters.ts';
import { buildPdfFromJpegPages } from '../../resources/js/scanner/pdf.ts';
import type { ImageDataLike } from '../../resources/js/scanner/quad-detector.ts';

function makeSyntheticDocumentImage(width: number, height: number): ImageDataLike {
    const data = new Uint8ClampedArray(width * height * 4);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const idx = (y * width + x) * 4;
            // Uneven shadowed cream/gray paper background (165..195)
            const shade = 165 + Math.round((x / Math.max(1, width - 1)) * 25);
            data[idx] = shade;
            data[idx + 1] = shade - 4;
            data[idx + 2] = shade - 10;
            data[idx + 3] = 255;

            // Dark text band in upper-left
            if (x >= 4 && x < 14 && y >= 4 && y < 10) {
                data[idx] = 42;
                data[idx + 1] = 40;
                data[idx + 2] = 44;
            }

            // Blue signature ink in lower-left
            if (x >= 4 && x < 14 && y >= 18 && y < 26) {
                data[idx] = 25;
                data[idx + 1] = 75;
                data[idx + 2] = 205;
            }

            // Red official stamp in lower-right
            if (x >= 18 && x < 28 && y >= 18 && y < 26) {
                data[idx] = 205;
                data[idx + 1] = 35;
                data[idx + 2] = 45;
            }
        }
    }

    return { width, height, data };
}

function readPixel(img: ImageDataLike, x: number, y: number): [number, number, number, number] {
    const idx = (y * img.width + x) * 4;

    return [img.data[idx], img.data[idx + 1], img.data[idx + 2], img.data[idx + 3]];
}

test('DEFAULT_SCAN_FILTER is documento and original filter preserves pixels unchanged', () => {
    assert.equal(DEFAULT_SCAN_FILTER, 'documento');

    const src = makeSyntheticDocumentImage(32, 32);
    const out = applyScanFilter(src, 'original');

    assert.equal(out.width, src.width);
    assert.equal(out.height, src.height);
    assert.deepEqual(Array.from(out.data), Array.from(src.data));
    assert.notEqual(out.data, src.data, 'original output should not alias input buffer');
});

test('documento filter whitens shadowed paper, sharpens dark text, and preserves colored stamps/signatures', () => {
    const src = makeSyntheticDocumentImage(32, 32);
    const out = applyScanFilter(src, 'documento');

    assert.equal(out.width, src.width);
    assert.equal(out.height, src.height);

    // Shadowed gray/cream paper becomes clean bright white
    const [bgR, bgG, bgB] = readPixel(out, 2, 2);
    assert.ok(bgR >= 240 && bgG >= 240 && bgB >= 240, `Expected bright white paper, got [${bgR},${bgG},${bgB}]`);

    const [bgRightR, bgRightG, bgRightB] = readPixel(out, 28, 2);
    assert.ok(
        bgRightR >= 240 && bgRightG >= 240 && bgRightB >= 240,
        `Expected bright white paper on right side, got [${bgRightR},${bgRightG},${bgRightB}]`,
    );

    // Dark text becomes darker / crisper than source (42, 40, 44)
    const [txtR, txtG, txtB] = readPixel(out, 8, 6);
    assert.ok(txtR < 35 && txtG < 35 && txtB < 35, `Expected crisp dark text, got [${txtR},${txtG},${txtB}]`);

    // Blue ink retains dominant blue channel and strong saturation
    const [blueR, blueG, blueB] = readPixel(out, 8, 22);
    assert.ok(
        blueB - blueR > 100 && blueB > blueG + 50,
        `Expected preserved blue ink, got [${blueR},${blueG},${blueB}]`,
    );

    // Red stamp retains dominant red channel and strong saturation
    const [redR, redG, redB] = readPixel(out, 22, 22);
    assert.ok(
        redR - redG > 100 && redR - redB > 100,
        `Expected preserved red stamp, got [${redR},${redG},${redB}]`,
    );
});

test('bw filter produces high-contrast achromatic (R === G === B) pixels', () => {
    const src = makeSyntheticDocumentImage(32, 32);
    const out = applyScanFilter(src, 'bw');

    for (let i = 0; i < out.data.length; i += 4) {
        assert.equal(out.data[i], out.data[i + 1], 'R must equal G');
        assert.equal(out.data[i + 1], out.data[i + 2], 'G must equal B');
        assert.equal(out.data[i + 3], 255, 'Alpha must be preserved');
    }

    // Shadowed paper becomes clean white
    const [bgR] = readPixel(out, 2, 2);
    assert.ok(bgR >= 245, `Expected white background in B&W, got ${bgR}`);

    // Dark text becomes solid black/near-black
    const [txtR] = readPixel(out, 8, 6);
    assert.ok(txtR <= 20, `Expected black text in B&W, got ${txtR}`);
});

test('documento and bw filters preserve faint thin text and diagram lines under strong phone shadows', () => {
    const width = 200;
    const height = 260;
    const data = new Uint8ClampedArray(width * height * 4);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const idx = (y * width + x) * 4;
            // Simulate a phone casting a shadow across the lower-center of the page (light behind phone):
            // lit outer paper ~235, deep shadowed paper ~125 with cool shadow tint
            const distFromShadowCenter = Math.hypot((x - 105) / 70, (y - 165) / 80);
            const shadowBlend = Math.max(0, Math.min(1, (distFromShadowCenter - 0.55) / 0.55));
            const baseR = Math.round(122 + (236 - 122) * shadowBlend);
            const baseG = Math.round(125 + (234 - 125) * shadowBlend);
            const baseB = Math.round(132 + (228 - 132) * shadowBlend);

            let r = baseR;
            let g = baseG;
            let b = baseB;

            // Thin 2px faint gray body text line in well-lit top region (rel ~0.80)
            if (y >= 40 && y <= 41 && x >= 30 && x <= 170) {
                r = Math.round(baseR * 0.80);
                g = Math.round(baseG * 0.80);
                b = Math.round(baseB * 0.80);
            }

            // Thin 2px faint gray body text line RIGHT INSIDE the phone shadow (rel ~0.80)
            if (y >= 164 && y <= 165 && x >= 30 && x <= 170) {
                r = Math.round(baseR * 0.80);
                g = Math.round(baseG * 0.80);
                b = Math.round(baseB * 0.80);
            }

            // Thin 1px light diagram line across shadow boundary (rel ~0.83)
            if (y === 110 && x >= 25 && x <= 175) {
                r = Math.round(baseR * 0.83);
                g = Math.round(baseG * 0.83);
                b = Math.round(baseB * 0.83);
            }

            data[idx] = r;
            data[idx + 1] = g;
            data[idx + 2] = b;
            data[idx + 3] = 255;
        }
    }

    const src: ImageDataLike = { width, height, data };
    const docOut = applyScanFilter(src, 'documento');
    const bwOut = applyScanFilter(src, 'bw');

    // Both lit paper (20, 20) and deep shadowed paper (105, 190) must normalize to clean bright white
    for (const [label, px, py] of [
        ['lit paper', 20, 20],
        ['shadowed paper', 105, 190],
        ['shadow penumbra paper', 105, 92],
    ] as const) {
        const [dr, dg, db] = readPixel(docOut, px, py);
        assert.ok(dr >= 242 && dg >= 242 && db >= 242, `documento ${label} should be clean white, got [${dr},${dg},${db}]`);

        const [br] = readPixel(bwOut, px, py);
        assert.ok(br >= 245, `bw ${label} should be clean white, got ${br}`);
    }

    // Faint thin text in lit region, inside deep shadow, and diagram line across shadow edge MUST NOT vanish to white!
    for (const [label, px, py] of [
        ['lit thin text', 100, 40],
        ['shadowed thin text', 105, 164],
        ['diagram line at shadow edge', 105, 110],
    ] as const) {
        const [dr, dg, db] = readPixel(docOut, px, py);
        const docLum = 0.299 * dr + 0.587 * dg + 0.114 * db;
        assert.ok(
            docLum <= 175,
            `documento ${label} must remain clearly legible (<= 175), got lum=${docLum.toFixed(1)} [${dr},${dg},${db}]`,
        );

        const [br] = readPixel(bwOut, px, py);
        assert.ok(
            br <= 150,
            `bw ${label} must remain clearly legible (<= 150), got ${br}`,
        );
    }
});

test('rotateImageData rotates 90/180/270 clockwise, swaps dimensions on quarter turns, and updates PDF MediaBox', () => {
    assert.equal(normalizeRotation(0), 0);
    assert.equal(normalizeRotation(360), 0);
    assert.equal(normalizeRotation(-90), 270);
    assert.equal(normalizeRotation(450), 90);

    // Asymmetric 3x2 image with distinct corner colors:
    // (0,0)=Red  (1,0)=Gray  (2,0)=Green
    // (0,1)=Blue (1,1)=White (2,1)=Black
    const src: ImageDataLike = {
        width: 3,
        height: 2,
        data: Uint8ClampedArray.from([
            255, 0, 0, 255,   120, 120, 120, 255,  0, 255, 0, 255,
            0, 0, 255, 255,   255, 255, 255, 255,  0, 0, 0, 255,
        ]),
    };

    const r90 = rotateImageData(src, 90);
    assert.equal(r90.width, 2);
    assert.equal(r90.height, 3);
    // 90° CW:
    // (0,0)=Blue (1,0)=Red
    // (0,1)=White (1,1)=Gray
    // (0,2)=Black (1,2)=Green
    assert.deepEqual(readPixel(r90, 0, 0), [0, 0, 255, 255]);
    assert.deepEqual(readPixel(r90, 1, 0), [255, 0, 0, 255]);
    assert.deepEqual(readPixel(r90, 0, 2), [0, 0, 0, 255]);
    assert.deepEqual(readPixel(r90, 1, 2), [0, 255, 0, 255]);

    const r180 = rotateImageData(src, 180);
    assert.equal(r180.width, 3);
    assert.equal(r180.height, 2);
    assert.deepEqual(readPixel(r180, 0, 0), [0, 0, 0, 255]);
    assert.deepEqual(readPixel(r180, 2, 1), [255, 0, 0, 255]);

    const r270 = rotateImageData(src, 270);
    assert.equal(r270.width, 2);
    assert.equal(r270.height, 3);
    assert.deepEqual(readPixel(r270, 0, 0), [0, 255, 0, 255]);
    assert.deepEqual(readPixel(r270, 1, 0), [0, 0, 0, 255]);
    assert.deepEqual(readPixel(r270, 0, 2), [255, 0, 0, 255]);
    assert.deepEqual(readPixel(r270, 1, 2), [0, 0, 255, 255]);

    // Verify portrait -> landscape aspect swap propagates into PDF MediaBox
    const minimalJpeg = Uint8Array.from([0xff, 0xd8, 0xff, 0xd9]);
    const portrait = rotateImageData({ width: 120, height: 160, data: new Uint8ClampedArray(120 * 160 * 4) }, 0);
    const landscape = rotateImageData({ width: 120, height: 160, data: new Uint8ClampedArray(120 * 160 * 4) }, 90);
    const pdfText = new TextDecoder('latin1').decode(
        buildPdfFromJpegPages([
            { jpegBytes: minimalJpeg, width: portrait.width, height: portrait.height },
            { jpegBytes: minimalJpeg, width: landscape.width, height: landscape.height },
        ]),
    );

    assert.match(pdfText, /\/MediaBox \[0 0 612 816\]/);
    assert.match(pdfText, /\/MediaBox \[0 0 612 459\]/);
});

test('movePage reorders pages purely without mutation and PDF reflects exact reordered sequence', () => {
    const p1 = { id: 'p1', jpegBytes: Uint8Array.from([0xff, 0xd8, 0x11, 0xff, 0xd9]), width: 100, height: 100 };
    const p2 = { id: 'p2', jpegBytes: Uint8Array.from([0xff, 0xd8, 0x22, 0xff, 0xd9]), width: 100, height: 150 };
    const p3 = { id: 'p3', jpegBytes: Uint8Array.from([0xff, 0xd8, 0x33, 0xff, 0xd9]), width: 100, height: 200 };
    const original = [p1, p2, p3];

    const reordered = movePage(original, 2, 0);
    assert.deepEqual(
        reordered.map((p) => p.id),
        ['p3', 'p1', 'p2'],
    );
    assert.deepEqual(
        original.map((p) => p.id),
        ['p1', 'p2', 'p3'],
        'original array must not be mutated',
    );

    // Out-of-bounds or no-op returns shallow copy with unchanged order
    assert.deepEqual(
        movePage(original, -1, 1).map((p) => p.id),
        ['p1', 'p2', 'p3'],
    );
    assert.deepEqual(
        movePage(original, 0, 5).map((p) => p.id),
        ['p1', 'p2', 'p3'],
    );

    const pdfText = new TextDecoder('latin1').decode(buildPdfFromJpegPages(reordered));
    const mediaBoxes = [...pdfText.matchAll(/\/MediaBox \[0 0 612 (\d+)\]/g)].map((m) => Number(m[1]));
    assert.deepEqual(mediaBoxes, [1224, 612, 918]);
});
