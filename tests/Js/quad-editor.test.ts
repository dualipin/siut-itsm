import test from 'node:test';
import assert from 'node:assert/strict';
import {
    formatQuadMaskPath,
    formatQuadPolygonPoints,
    normalizePointerQuadCorner,
} from '../../resources/js/scanner/quad-editor.ts';
import type { DocumentQuad } from '../../resources/js/scanner/quad-detector.ts';

test('normalizePointerQuadCorner maps stage pointer coordinates to clamped [0, 1] space', () => {
    const rect = { left: 100, top: 50, width: 200, height: 400 };

    assert.deepEqual(normalizePointerQuadCorner(200, 250, rect), { x: 0.5, y: 0.5 });
    assert.deepEqual(normalizePointerQuadCorner(100, 50, rect), { x: 0, y: 0 });
    assert.deepEqual(normalizePointerQuadCorner(300, 450, rect), { x: 1, y: 1 });

    // Clamps pointer coordinates outside the stage bounds
    assert.deepEqual(normalizePointerQuadCorner(20, -10, rect), { x: 0, y: 0 });
    assert.deepEqual(normalizePointerQuadCorner(500, 900, rect), { x: 1, y: 1 });
});

test('normalizePointerQuadCorner returns null on degenerate stage rect or non-finite pointer', () => {
    assert.equal(normalizePointerQuadCorner(10, 10, { left: 0, top: 0, width: 0, height: 100 }), null);
    assert.equal(normalizePointerQuadCorner(10, 10, { left: 0, top: 0, width: 100, height: -5 }), null);
    assert.equal(normalizePointerQuadCorner(Number.NaN, 10, { left: 0, top: 0, width: 100, height: 100 }), null);
});

test('formatQuadPolygonPoints and formatQuadMaskPath format 0..100 SVG geometry cleanly', () => {
    const quad: DocumentQuad = {
        topLeft: { x: 0.1, y: 0.2 },
        topRight: { x: 0.9, y: 0.25 },
        bottomRight: { x: 0.85, y: 0.9 },
        bottomLeft: { x: 0.15, y: 0.8 },
    };

    assert.equal(formatQuadPolygonPoints(quad), '10,20 90,25 85,90 15,80');
    assert.equal(formatQuadMaskPath(quad), 'M0,0 H100 V100 H0 Z M10,20 L90,25 L85,90 L15,80 Z');

    assert.equal(formatQuadPolygonPoints(null), '');
    assert.equal(formatQuadMaskPath(undefined), '');
});
