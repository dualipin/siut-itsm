import test from 'node:test';
import assert from 'node:assert/strict';
import {
    FULL_DOCUMENT_QUAD,
    detectDocumentQuad,
    isValidConvexQuad,
    type DocumentQuad,
    type ImageDataLike,
    type Point2D,
} from '../../resources/js/scanner/quad-detector.ts';

function pointInPolygon(px: number, py: number, poly: Point2D[]): boolean {
    let inside = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
        const xi = poly[i].x;
        const yi = poly[i].y;
        const xj = poly[j].x;
        const yj = poly[j].y;

        const intersect = ((yi > py) !== (yj > py))
            && (px < ((xj - xi) * (py - yi)) / (yj - yi) + xi);
        if (intersect) {
            inside = !inside;
        }
    }

    return inside;
}

function drawQuadImage(
    width: number,
    height: number,
    quad: DocumentQuad,
    bgRgba: [number, number, number, number],
    fgRgba: [number, number, number, number],
    withSimulatedText: boolean = false,
): ImageDataLike {
    const img = createBlankImage(width, height, bgRgba);
    const poly: Point2D[] = [
        { x: quad.topLeft.x * (width - 1), y: quad.topLeft.y * (height - 1) },
        { x: quad.topRight.x * (width - 1), y: quad.topRight.y * (height - 1) },
        { x: quad.bottomRight.x * (width - 1), y: quad.bottomRight.y * (height - 1) },
        { x: quad.bottomLeft.x * (width - 1), y: quad.bottomLeft.y * (height - 1) },
    ];

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (!pointInPolygon(x, y, poly)) {
                continue;
            }

            const idx = (y * width + x) * 4;
            const isTextLine = withSimulatedText
                && y % 12 >= 5
                && y % 12 <= 7
                && x > width * 0.28
                && x < width * 0.72
                && y > height * 0.28
                && y < height * 0.72;

            const color = isTextLine ? [30, 30, 35, 255] : fgRgba;
            img.data[idx] = color[0];
            img.data[idx + 1] = color[1];
            img.data[idx + 2] = color[2];
            img.data[idx + 3] = color[3];
        }
    }

    return img;
}

function assertPointClose(actual: Point2D, expected: Point2D, tolerance: number, label: string): void {
    const dist = Math.hypot(actual.x - expected.x, actual.y - expected.y);
    assert.ok(
        dist <= tolerance,
        `${label}: expected (${expected.x.toFixed(3)}, ${expected.y.toFixed(3)}), got (${actual.x.toFixed(3)}, ${actual.y.toFixed(3)}) [dist=${dist.toFixed(4)}]`,
    );
}

function createBlankImage(
    width: number,
    height: number,
    rgba: [number, number, number, number],
): ImageDataLike {
    const data = new Uint8ClampedArray(width * height * 4);

    for (let i = 0; i < data.length; i += 4) {
        data[i] = rgba[0];
        data[i + 1] = rgba[1];
        data[i + 2] = rgba[2];
        data[i + 3] = rgba[3];
    }

    return { width, height, data };
}

test('falls back to full safe bounds on uniform or low-contrast images', () => {
    const uniformGray = createBlankImage(160, 200, [180, 180, 180, 255]);
    const uniformResult = detectDocumentQuad(uniformGray);

    assert.equal(uniformResult.detected, false);
    assert.deepEqual(uniformResult.quad, FULL_DOCUMENT_QUAD);

    // Subtle noise within 8 intensity levels should still trigger safe fallback
    const noisy = createBlankImage(160, 200, [210, 210, 210, 255]);
    for (let y = 0; y < noisy.height; y++) {
        for (let x = 0; x < noisy.width; x++) {
            const idx = (y * noisy.width + x) * 4;
            const delta = ((x * 7 + y * 13) % 9) - 4;
            noisy.data[idx] = 210 + delta;
            noisy.data[idx + 1] = 210 + delta;
            noisy.data[idx + 2] = 210 + delta;
        }
    }

    const noisyResult = detectDocumentQuad(noisy);
    assert.equal(noisyResult.detected, false);
    assert.deepEqual(noisyResult.quad, FULL_DOCUMENT_QUAD);
});

test('detects tilted perspective document quad on contrasting background even with interior text lines', () => {
    const expectedQuad: DocumentQuad = {
        topLeft: { x: 0.18, y: 0.14 },
        topRight: { x: 0.84, y: 0.19 },
        bottomRight: { x: 0.90, y: 0.86 },
        bottomLeft: { x: 0.11, y: 0.83 },
    };

    const synthetic = drawQuadImage(
        160,
        200,
        expectedQuad,
        [55, 42, 34, 255],
        [242, 240, 235, 255],
        true,
    );

    const result = detectDocumentQuad(synthetic);

    assert.equal(result.detected, true);
    assert.ok(result.confidence > 0.5);
    assertPointClose(result.quad.topLeft, expectedQuad.topLeft, 0.03, 'topLeft');
    assertPointClose(result.quad.topRight, expectedQuad.topRight, 0.03, 'topRight');
    assertPointClose(result.quad.bottomRight, expectedQuad.bottomRight, 0.03, 'bottomRight');
    assertPointClose(result.quad.bottomLeft, expectedQuad.bottomLeft, 0.03, 'bottomLeft');
});

test('detects dark contrasting sheet on bright background', () => {
    const expectedQuad: DocumentQuad = {
        topLeft: { x: 0.15, y: 0.12 },
        topRight: { x: 0.82, y: 0.15 },
        bottomRight: { x: 0.86, y: 0.88 },
        bottomLeft: { x: 0.13, y: 0.85 },
    };

    const synthetic = drawQuadImage(
        160,
        200,
        expectedQuad,
        [235, 235, 238, 255],
        [45, 48, 52, 255],
        false,
    );

    const result = detectDocumentQuad(synthetic);

    assert.equal(result.detected, true);
    assertPointClose(result.quad.topLeft, expectedQuad.topLeft, 0.03, 'topLeft');
    assertPointClose(result.quad.topRight, expectedQuad.topRight, 0.03, 'topRight');
    assertPointClose(result.quad.bottomRight, expectedQuad.bottomRight, 0.03, 'bottomRight');
    assertPointClose(result.quad.bottomLeft, expectedQuad.bottomLeft, 0.03, 'bottomLeft');
});

test('falls back safely when bright spot is too small or sheet fills the frame', () => {
    // Small 15x15 bright spot in 160x200 dark image (< 2% area)
    const tinySpotQuad: DocumentQuad = {
        topLeft: { x: 0.45, y: 0.45 },
        topRight: { x: 0.55, y: 0.45 },
        bottomRight: { x: 0.55, y: 0.55 },
        bottomLeft: { x: 0.45, y: 0.55 },
    };
    const tinySpotImg = drawQuadImage(160, 200, tinySpotQuad, [30, 30, 30, 255], [240, 240, 240, 255]);
    const tinyResult = detectDocumentQuad(tinySpotImg);

    assert.equal(tinyResult.detected, false);
    assert.deepEqual(tinyResult.quad, FULL_DOCUMENT_QUAD);

    // Sheet fills entire frame with dark text lines inside (must NOT crop onto a text block)
    const fullSheet = createBlankImage(160, 200, [245, 245, 245, 255]);
    for (let y = 20; y < 180; y += 14) {
        for (let x = 15; x < 145; x++) {
            for (let dy = 0; dy < 3; dy++) {
                const idx = ((y + dy) * fullSheet.width + x) * 4;
                fullSheet.data[idx] = 25;
                fullSheet.data[idx + 1] = 25;
                fullSheet.data[idx + 2] = 25;
            }
        }
    }

    const fullSheetResult = detectDocumentQuad(fullSheet);
    assert.equal(fullSheetResult.detected, false);
    assert.deepEqual(fullSheetResult.quad, FULL_DOCUMENT_QUAD);
});

test('validates convex quads and rejects self-intersecting bowtie quads', () => {
    assert.equal(isValidConvexQuad(FULL_DOCUMENT_QUAD), true);
    assert.equal(
        isValidConvexQuad({
            topLeft: { x: 0.1, y: 0.1 },
            topRight: { x: 0.9, y: 0.9 }, // swapped with bottomRight -> self-intersecting
            bottomRight: { x: 0.9, y: 0.1 },
            bottomLeft: { x: 0.1, y: 0.9 },
        }),
        false,
    );
});
