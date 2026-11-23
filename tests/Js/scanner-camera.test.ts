import test from 'node:test';
import assert from 'node:assert/strict';
import {
    assessCameraEnvironment,
    buildRearCameraConstraints,
    buildTorchConstraints,
    mapCameraErrorToSpanish,
    supportsTrackTorch,
} from '../../resources/js/scanner/camera.ts';

test('assesses camera environment and requires secure context plus getUserMedia support', () => {
    const ready = assessCameraEnvironment({
        isSecureContext: true,
        hasMediaDevices: true,
        hasGetUserMedia: true,
    });
    assert.equal(ready.supported, true);
    assert.equal(ready.reason, null);
    assert.equal(ready.message, null);

    const insecure = assessCameraEnvironment({
        isSecureContext: false,
        hasMediaDevices: true,
        hasGetUserMedia: true,
    });
    assert.equal(insecure.supported, false);
    assert.equal(insecure.reason, 'insecure-context');
    assert.match(insecure.message ?? '', /HTTPS|localhost/i);

    const unsupported = assessCameraEnvironment({
        isSecureContext: true,
        hasMediaDevices: false,
        hasGetUserMedia: false,
    });
    assert.equal(unsupported.supported, false);
    assert.equal(unsupported.reason, 'unsupported');
    assert.match(unsupported.message ?? '', /galer[ií]a|Tomar foto/i);
});

test('builds rear-camera high-resolution constraints for continuous document capture', () => {
    const defaults = buildRearCameraConstraints();
    assert.deepEqual(defaults, {
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 3840 },
            height: { ideal: 2160 },
        },
    });

    const custom = buildRearCameraConstraints({ idealWidth: 2560, idealHeight: 1440 });
    assert.deepEqual(custom, {
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 2560 },
            height: { ideal: 1440 },
        },
    });
});

test('detects torch capability safely and builds track torch constraints', () => {
    assert.equal(supportsTrackTorch(undefined), false);
    assert.equal(supportsTrackTorch(null), false);
    assert.equal(supportsTrackTorch({}), false);
    assert.equal(supportsTrackTorch({ torch: false }), false);
    assert.equal(supportsTrackTorch({ torch: true }), true);

    assert.deepEqual(buildTorchConstraints(true), {
        advanced: [{ torch: true }],
    });
    assert.deepEqual(buildTorchConstraints(false), {
        advanced: [{ torch: false }],
    });
});

test('maps getUserMedia errors to clear Spanish fallback messages', () => {
    const denied = mapCameraErrorToSpanish({ name: 'NotAllowedError', message: 'Permission denied' });
    assert.equal(denied.code, 'permission-denied');
    assert.match(denied.message, /permiso/i);

    const notFound = mapCameraErrorToSpanish({ name: 'NotFoundError', message: 'Requested device not found' });
    assert.equal(notFound.code, 'not-found');
    assert.match(notFound.message, /c[aá]mara/i);

    const busy = mapCameraErrorToSpanish({ name: 'NotReadableError', message: 'Could not start video source' });
    assert.equal(busy.code, 'not-readable');
    assert.match(busy.message, /otra aplicaci[oó]n|ocupada|disponible/i);

    const overconstrained = mapCameraErrorToSpanish({ name: 'OverconstrainedError', message: 'Constraint' });
    assert.equal(overconstrained.code, 'overconstrained');
    assert.match(overconstrained.message, /galer[ií]a|Tomar foto/i);

    const unknown = mapCameraErrorToSpanish(new Error('Unexpected'));
    assert.equal(unknown.code, 'unknown');
    assert.match(unknown.message, /galer[ií]a|Tomar foto/i);
});
