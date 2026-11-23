import test from 'node:test';
import assert from 'node:assert/strict';
import {
    computeWarpedDimensions,
    DEFAULT_MAX_LONG_SIDE,
    LETTER_LONG_SIDE_INCHES,
    projectPoint,
    SCAN_TARGET_DPI,
    solveHomography,
    warpPerspective,
} from '../../resources/js/scanner/perspective.ts';
import type { DocumentQuad, ImageDataLike, Point2D } from '../../resources/js/scanner/quad-detector.ts';

test('computes realistic output aspect ratio and caps long side at 250 DPI default maxLongSide', () => {
    assert.equal(SCAN_TARGET_DPI, 250);
    assert.equal(LETTER_LONG_SIDE_INCHES, 11);
    assert.equal(DEFAULT_MAX_LONG_SIDE, 2750);

    // Full-frame 3000x4000 portrait image -> 2063x2750
    const fullQuad: DocumentQuad = {
        topLeft: { x: 0, y: 0 },
        topRight: { x: 1, y: 0 },
        bottomRight: { x: 1, y: 1 },
        bottomLeft: { x: 0, y: 1 },
    };
    const portrait = computeWarpedDimensions(fullQuad, 3000, 4000);
    assert.equal(portrait.width, 2063);
    assert.equal(portrait.height, 2750);
    assert.ok(Math.max(portrait.width, portrait.height) <= 2750);

    // US Letter (8.5x11) high-res capture -> exact 2125x2750 (250 DPI)
    const letter = computeWarpedDimensions(fullQuad, 3400, 4400);
    assert.equal(letter.width, 2125);
    assert.equal(letter.height, 2750);

    // Landscape sub-quad 3200x2000 within 4000x3000 -> 2750x1719
    const landscapeQuad: DocumentQuad = {
        topLeft: { x: 0.1, y: 0.1 },
        topRight: { x: 0.9, y: 0.1 },
        bottomRight: { x: 0.9, y: 0.7667 },
        bottomLeft: { x: 0.1, y: 0.7667 },
    };
    const landscape = computeWarpedDimensions(landscapeQuad, 4000, 3000);
    assert.equal(landscape.width, 2750);
    assert.equal(landscape.height, 1719);
    assert.ok(Math.max(landscape.width, landscape.height) <= 2750);

    // Small image 600x800 is not upscaled
    const small = computeWarpedDimensions(fullQuad, 600, 800);
    assert.equal(small.width, 600);
    assert.equal(small.height, 800);
});

test('solves 3x3 homography mapping destination rectangle corners back to tilted source quad accurately', () => {
    const outW = 400;
    const outH = 500;
    const dstCorners: [Point2D, Point2D, Point2D, Point2D] = [
        { x: 0, y: 0 },
        { x: outW - 1, y: 0 },
        { x: outW - 1, y: outH - 1 },
        { x: 0, y: outH - 1 },
    ];
    const srcCorners: [Point2D, Point2D, Point2D, Point2D] = [
        { x: 140, y: 80 },
        { x: 660, y: 120 },
        { x: 740, y: 880 },
        { x: 60, y: 840 },
    ];

    const H = solveHomography(dstCorners, srcCorners);

    for (let i = 0; i < 4; i++) {
        const projected = projectPoint(H, dstCorners[i].x, dstCorners[i].y);
        assert.ok(Math.abs(projected.x - srcCorners[i].x) < 1e-6);
        assert.ok(Math.abs(projected.y - srcCorners[i].y) < 1e-6);
    }

    // Center of destination maps to the exact projective intersection of the source quad diagonals:
    // t = 83/190 -> (402.1053, 429.4737)
    const center = projectPoint(H, (outW - 1) / 2, (outH - 1) / 2);
    assert.ok(Math.abs(center.x - 402.1053) < 1e-3);
    assert.ok(Math.abs(center.y - 429.4737) < 1e-3);
});

function getPixel(img: ImageDataLike, x: number, y: number): [number, number, number, number] {
    const cx = Math.max(0, Math.min(img.width - 1, Math.round(x)));
    const cy = Math.max(0, Math.min(img.height - 1, Math.round(y)));
    const idx = (cy * img.width + cx) * 4;

    return [img.data[idx], img.data[idx + 1], img.data[idx + 2], img.data[idx + 3]];
}

test('warps tilted trapezoidal sheet into upright rectangle with bilinear sampling and no border bleed', () => {
    const srcW = 240;
    const srcH = 300;
    const data = new Uint8ClampedArray(srcW * srcH * 4);

    // Dark background
    for (let i = 0; i < data.length; i += 4) {
        data[i] = 20;
        data[i + 1] = 20;
        data[i + 2] = 25;
        data[i + 3] = 255;
    }

    const quad: DocumentQuad = {
        topLeft: { x: 0.20, y: 0.14 },
        topRight: { x: 0.82, y: 0.20 },
        bottomRight: { x: 0.90, y: 0.86 },
        bottomLeft: { x: 0.10, y: 0.82 },
    };

    // Map upright [0..1]x[0..1] -> source pixel coords to paint a realistic perspective-distorted sheet
    const srcQuadPx: [Point2D, Point2D, Point2D, Point2D] = [
        { x: quad.topLeft.x * (srcW - 1), y: quad.topLeft.y * (srcH - 1) },
        { x: quad.topRight.x * (srcW - 1), y: quad.topRight.y * (srcH - 1) },
        { x: quad.bottomRight.x * (srcW - 1), y: quad.bottomRight.y * (srcH - 1) },
        { x: quad.bottomLeft.x * (srcW - 1), y: quad.bottomLeft.y * (srcH - 1) },
    ];
    const unitRect: [Point2D, Point2D, Point2D, Point2D] = [
        { x: 0, y: 0 },
        { x: 1, y: 0 },
        { x: 1, y: 1 },
        { x: 0, y: 1 },
    ];
    const srcToUnit = solveHomography(srcQuadPx, unitRect);

    for (let y = 0; y < srcH; y++) {
        for (let x = 0; x < srcW; x++) {
            const uv = projectPoint(srcToUnit, x, y);
            if (uv.x < 0 || uv.x > 1 || uv.y < 0 || uv.y > 1) {
                continue;
            }

            let rgb: [number, number, number] = [245, 245, 245];
            if (Math.hypot(uv.x - 0.22, uv.y - 0.22) < 0.08) {
                rgb = [220, 30, 30]; // TL red
            } else if (Math.hypot(uv.x - 0.78, uv.y - 0.22) < 0.08) {
                rgb = [30, 200, 40]; // TR green
            } else if (Math.hypot(uv.x - 0.78, uv.y - 0.78) < 0.08) {
                rgb = [30, 60, 220]; // BR blue
            } else if (Math.hypot(uv.x - 0.22, uv.y - 0.78) < 0.08) {
                rgb = [20, 20, 20]; // BL black
            }

            const idx = (y * srcW + x) * 4;
            data[idx] = rgb[0];
            data[idx + 1] = rgb[1];
            data[idx + 2] = rgb[2];
        }
    }

    const warped = warpPerspective({ width: srcW, height: srcH, data }, quad, { maxLongSide: 1600 });

    assert.ok(warped.width > 120 && warped.height > 120);
    assert.ok(Math.max(warped.width, warped.height) <= 1600);

    // Corners of warped output should be clean white paper (not dark background)
    for (const [cx, cy] of [
        [2, 2],
        [warped.width - 3, 2],
        [warped.width - 3, warped.height - 3],
        [2, warped.height - 3],
    ]) {
        const [r, g, b] = getPixel(warped, cx, cy);
        assert.ok(r > 200 && g > 200 && b > 200, `Corner (${cx},${cy}) expected white paper, got [${r},${g},${b}]`);
    }

    // Quadrant markers recovered cleanly at upright positions
    const tl = getPixel(warped, warped.width * 0.22, warped.height * 0.22);
    assert.ok(tl[0] > 180 && tl[1] < 80 && tl[2] < 80, `TL expected red, got [${tl}]`);

    const tr = getPixel(warped, warped.width * 0.78, warped.height * 0.22);
    assert.ok(tr[1] > 160 && tr[0] < 80 && tr[2] < 80, `TR expected green, got [${tr}]`);

    const br = getPixel(warped, warped.width * 0.78, warped.height * 0.78);
    assert.ok(br[2] > 180 && br[0] < 80 && br[1] < 100, `BR expected blue, got [${br}]`);

    const bl = getPixel(warped, warped.width * 0.22, warped.height * 0.78);
    assert.ok(bl[0] < 60 && bl[1] < 60 && bl[2] < 60, `BL expected black, got [${bl}]`);
});

test('rejects invalid self-intersecting quad in warpPerspective', () => {
    const img: ImageDataLike = {
        width: 20,
        height: 20,
        data: new Uint8ClampedArray(20 * 20 * 4),
    };

    assert.throws(
        () => warpPerspective(img, {
            topLeft: { x: 0.1, y: 0.1 },
            topRight: { x: 0.9, y: 0.9 },
            bottomRight: { x: 0.9, y: 0.1 },
            bottomLeft: { x: 0.1, y: 0.9 },
        }),
        /cuadrilátero|esquinas/i,
    );
});
