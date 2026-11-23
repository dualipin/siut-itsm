import { clamp01, clampByte } from './image-utils.ts';
import type { ImageDataLike } from './quad-detector.ts';

export type ScanFilter = 'documento' | 'bw' | 'original';

export type QuarterTurnRotation = 0 | 90 | 180 | 270;

export const DEFAULT_SCAN_FILTER: ScanFilter = 'documento';

const BG_MAX_SIDE = 160;

// Precomputed 1024-entry LUT for fast neutral gamma curve u^2.2 (B&W) and u^1.85 (Documento)
const LUT_SIZE = 1024;
const BW_GAMMA_LUT = new Uint8Array(LUT_SIZE + 1);
const DOC_NEUTRAL_GAMMA_LUT = new Float32Array(LUT_SIZE + 1);

for (let i = 0; i <= LUT_SIZE; i++) {
    const u = i / LUT_SIZE;
    BW_GAMMA_LUT[i] = clampByte(255 * Math.pow(u, 2.2));
    DOC_NEUTRAL_GAMMA_LUT[i] = Math.pow(u, 1.85);
}

interface LocalBackgroundRGB {
    bgW: number;
    bgH: number;
    r: Float32Array;
    g: Float32Array;
    b: Float32Array;
}

interface AxisSampleLut {
    i0: Int32Array;
    i1: Int32Array;
    f: Float32Array;
}

function smoothstep01(t: number): number {
    const x = clamp01(t);

    return x * x * (3 - 2 * x);
}

function buildAxisSampleLut(size: number, gridSize: number): AxisSampleLut {
    const i0 = new Int32Array(size);
    const i1 = new Int32Array(size);
    const f = new Float32Array(size);

    if (gridSize <= 1) {
        return { i0, i1, f };
    }

    const maxIdx = gridSize - 1;
    const scale = gridSize / size;

    for (let p = 0; p < size; p++) {
        const u = (p + 0.5) * scale - 0.5;
        let idx0 = Math.floor(u);
        if (idx0 < 0) {
            idx0 = 0;
        } else if (idx0 > maxIdx) {
            idx0 = maxIdx;
        }
        const idx1 = idx0 < maxIdx ? idx0 + 1 : maxIdx;
        let frac = u - idx0;
        if (frac < 0) {
            frac = 0;
        } else if (frac > 1) {
            frac = 1;
        }
        i0[p] = idx0;
        i1[p] = idx1;
        f[p] = frac;
    }

    return { i0, i1, f };
}

function separableMaxFilter(
    src: Float32Array,
    width: number,
    height: number,
    radius: number,
): Float32Array {
    const tmp = new Float32Array(width * height);
    const dst = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - radius);
            const x1 = Math.min(width - 1, x + radius);
            let best = src[row + x0];
            for (let kx = x0 + 1; kx <= x1; kx++) {
                const v = src[row + kx];
                if (v > best) {
                    best = v;
                }
            }
            tmp[row + x] = best;
        }
    }

    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - radius);
        const y1 = Math.min(height - 1, y + radius);
        const row = y * width;
        for (let x = 0; x < width; x++) {
            let best = tmp[y0 * width + x];
            for (let ky = y0 + 1; ky <= y1; ky++) {
                const v = tmp[ky * width + x];
                if (v > best) {
                    best = v;
                }
            }
            dst[row + x] = best;
        }
    }

    return dst;
}

function separableMinFilter(
    src: Float32Array,
    width: number,
    height: number,
    radius: number,
): Float32Array {
    const tmp = new Float32Array(width * height);
    const dst = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - radius);
            const x1 = Math.min(width - 1, x + radius);
            let best = src[row + x0];
            for (let kx = x0 + 1; kx <= x1; kx++) {
                const v = src[row + kx];
                if (v < best) {
                    best = v;
                }
            }
            tmp[row + x] = best;
        }
    }

    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - radius);
        const y1 = Math.min(height - 1, y + radius);
        const row = y * width;
        for (let x = 0; x < width; x++) {
            let best = tmp[y0 * width + x];
            for (let ky = y0 + 1; ky <= y1; ky++) {
                const v = tmp[ky * width + x];
                if (v < best) {
                    best = v;
                }
            }
            dst[row + x] = best;
        }
    }

    return dst;
}

function separableBoxBlur(
    src: Float32Array,
    width: number,
    height: number,
    radius: number,
): Float32Array {
    if (radius <= 0) {
        return src;
    }

    const tmp = new Float32Array(width * height);
    const dst = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - radius);
            const x1 = Math.min(width - 1, x + radius);
            let sum = 0;
            for (let kx = x0; kx <= x1; kx++) {
                sum += src[row + kx];
            }
            tmp[row + x] = sum / (x1 - x0 + 1);
        }
    }

    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - radius);
        const y1 = Math.min(height - 1, y + radius);
        const row = y * width;
        const count = y1 - y0 + 1;
        for (let x = 0; x < width; x++) {
            let sum = 0;
            for (let ky = y0; ky <= y1; ky++) {
                sum += tmp[ky * width + x];
            }
            dst[row + x] = sum / count;
        }
    }

    return dst;
}

function percentile90(values: Float32Array): number {
    const hist = new Int32Array(256);
    for (let i = 0; i < values.length; i++) {
        hist[clampByte(values[i])]++;
    }

    const target = Math.max(1, Math.floor(values.length * 0.9));
    let acc = 0;
    for (let bin = 0; bin < 256; bin++) {
        acc += hist[bin];
        if (acc >= target) {
            return bin;
        }
    }

    return 220;
}

function estimateChannelBackground(
    channel: Float32Array,
    bgW: number,
    bgH: number,
    closeRadius: number,
    smoothRadius: number,
): Float32Array {
    const globalPaper = percentile90(channel);
    // Allow deep phone shadows (down to ~35% of global paper brightness) without clamping prematurely,
    // while preventing solid black photos/graphics from dividing by near-zero values.
    const shadowSafeFloor = Math.max(48, globalPaper * 0.35);

    const dilated = separableMaxFilter(channel, bgW, bgH, closeRadius);
    const closed = separableMinFilter(dilated, bgW, bgH, closeRadius);
    const smoothed = separableBoxBlur(closed, bgW, bgH, smoothRadius);

    for (let i = 0; i < smoothed.length; i++) {
        if (smoothed[i] < shadowSafeFloor) {
            smoothed[i] = shadowSafeFloor;
        }
    }

    return smoothed;
}

function computeLocalBackgroundRGB(
    data: Uint8ClampedArray | Uint8Array,
    width: number,
    height: number,
): LocalBackgroundRGB {
    const longSide = Math.max(width, height);
    const scale = longSide > BG_MAX_SIDE ? BG_MAX_SIDE / longSide : 1;
    const bgW = Math.max(1, Math.round(width * scale));
    const bgH = Math.max(1, Math.round(height * scale));
    const total = bgW * bgH;

    const rawR = new Float32Array(total);
    const rawG = new Float32Array(total);
    const rawB = new Float32Array(total);

    if (bgW === width && bgH === height) {
        for (let i = 0; i < total; i++) {
            const o = i * 4;
            rawR[i] = data[o];
            rawG[i] = data[o + 1];
            rawB[i] = data[o + 2];
        }
    } else {
        for (let by = 0; by < bgH; by++) {
            const sy0 = Math.floor((by * height) / bgH);
            const sy1 = Math.max(sy0 + 1, Math.min(height, Math.ceil(((by + 1) * height) / bgH)));
            const yStep = Math.max(1, Math.floor((sy1 - sy0) / 3));

            for (let bx = 0; bx < bgW; bx++) {
                const sx0 = Math.floor((bx * width) / bgW);
                const sx1 = Math.max(sx0 + 1, Math.min(width, Math.ceil(((bx + 1) * width) / bgW)));
                const xStep = Math.max(1, Math.floor((sx1 - sx0) / 3));

                let sumR = 0;
                let sumG = 0;
                let sumB = 0;
                let maxLum = -1;
                let maxR = 0;
                let maxG = 0;
                let maxB = 0;
                let count = 0;

                for (let sy = sy0; sy < sy1; sy += yStep) {
                    const rowOffset = sy * width;
                    for (let sx = sx0; sx < sx1; sx += xStep) {
                        const o = (rowOffset + sx) * 4;
                        const r = data[o];
                        const g = data[o + 1];
                        const b = data[o + 2];
                        const lum = 0.299 * r + 0.587 * g + 0.114 * b;
                        sumR += r;
                        sumG += g;
                        sumB += b;
                        count++;
                        if (lum > maxLum) {
                            maxLum = lum;
                            maxR = r;
                            maxG = g;
                            maxB = b;
                        }
                    }
                }

                const inv = 1 / Math.max(1, count);
                const idx = by * bgW + bx;
                // Blend cell brightest sample (70%) and mean (30%) to resist specular hot spots while ignoring thin ink.
                rawR[idx] = maxR * 0.7 + sumR * inv * 0.3;
                rawG[idx] = maxG * 0.7 + sumG * inv * 0.3;
                rawB[idx] = maxB * 0.7 + sumB * inv * 0.3;
            }
        }
    }

    const minBgSide = Math.min(bgW, bgH);
    const closeRadius = longSide <= 96
        ? Math.max(5, Math.round(minBgSide * 0.22))
        : Math.max(4, Math.min(8, Math.round(minBgSide * 0.045)));
    const smoothRadius = Math.max(1, Math.min(2, Math.round(closeRadius * 0.3)));

    return {
        bgW,
        bgH,
        r: estimateChannelBackground(rawR, bgW, bgH, closeRadius, smoothRadius),
        g: estimateChannelBackground(rawG, bgW, bgH, closeRadius, smoothRadius),
        b: estimateChannelBackground(rawB, bgW, bgH, closeRadius, smoothRadius),
    };
}

function blurRow1D(src: Float32Array, rowOffset: number, width: number, dst: Float32Array): void {
    if (width === 1) {
        dst[0] = src[rowOffset];

        return;
    }

    dst[0] = (3 * src[rowOffset] + src[rowOffset + 1]) * 0.25;
    for (let x = 1; x < width - 1; x++) {
        dst[x] = (src[rowOffset + x - 1] + 2 * src[rowOffset + x] + src[rowOffset + x + 1]) * 0.25;
    }
    dst[width - 1] = (src[rowOffset + width - 2] + 3 * src[rowOffset + width - 1]) * 0.25;
}

function sharpenRelativeLuminance(relLum: number, blurredRel: number): number {
    const detail = relLum - blurredRel;
    const deadzone = 0.018;

    // Asymmetric deadzoned unsharp mask: deepens thin dark strokes/lines without amplifying blank paper grain.
    if (detail < -deadzone) {
        const v = relLum + (detail + deadzone) * 1.15;

        return v < 0 ? 0 : v;
    }

    if (detail > deadzone) {
        const v = relLum + (detail - deadzone) * 0.30;

        return v > 1.15 ? 1.15 : v;
    }

    return relLum;
}

export function applyScanFilter(source: ImageDataLike, filter: ScanFilter): ImageDataLike {
    const { width, height, data } = source;
    const expectedLength = width * height * 4;

    if (width <= 0 || height <= 0 || data.length < expectedLength) {
        throw new Error('Las dimensiones de la imagen para aplicar filtro no son válidas.');
    }

    if (filter === 'original') {
        return {
            width,
            height,
            data: new Uint8ClampedArray(data.subarray(0, expectedLength)),
        };
    }

    const total = width * height;
    const bg = computeLocalBackgroundRGB(data, width, height);
    const { bgW, bgH, r: bgGridR, g: bgGridG, b: bgGridB } = bg;

    const xLut = buildAxisSampleLut(width, bgW);
    const yLut = buildAxisSampleLut(height, bgH);
    const x0Arr = xLut.i0;
    const x1Arr = xLut.i1;
    const fxArr = xLut.f;
    const y0Arr = yLut.i0;
    const y1Arr = yLut.i1;
    const fyArr = yLut.f;

    // Pass 1: Compute per-pixel illumination-normalized relative luminance using precomputed 1D grid tables.
    const relLum = new Float32Array(total);

    for (let y = 0; y < height; y++) {
        const row = y * width;
        const gy0 = y0Arr[y] * bgW;
        const gy1 = y1Arr[y] * bgW;
        const fy = fyArr[y];
        const invFy = 1 - fy;

        for (let x = 0; x < width; x++) {
            const gx0 = x0Arr[x];
            const gx1 = x1Arr[x];
            const fx = fxArr[x];
            const invFx = 1 - fx;

            const w00 = invFx * invFy;
            const w10 = fx * invFy;
            const w01 = invFx * fy;
            const w11 = fx * fy;

            const k00 = gy0 + gx0;
            const k10 = gy0 + gx1;
            const k01 = gy1 + gx0;
            const k11 = gy1 + gx1;

            const bgR = bgGridR[k00] * w00 + bgGridR[k10] * w10 + bgGridR[k01] * w01 + bgGridR[k11] * w11;
            const bgG = bgGridG[k00] * w00 + bgGridG[k10] * w10 + bgGridG[k01] * w01 + bgGridG[k11] * w11;
            const bgB = bgGridB[k00] * w00 + bgGridB[k10] * w10 + bgGridB[k01] * w01 + bgGridB[k11] * w11;

            const i = row + x;
            const o = i * 4;
            let nr = data[o] / bgR;
            let ng = data[o + 1] / bgG;
            let nb = data[o + 2] / bgB;
            if (nr > 1.15) {
                nr = 1.15;
            }
            if (ng > 1.15) {
                ng = 1.15;
            }
            if (nb > 1.15) {
                nb = 1.15;
            }

            relLum[i] = 0.299 * nr + 0.587 * ng + 0.114 * nb;
        }
    }

    // Pass 2: Rolling 3-row separable 3x3 Gaussian blur + asymmetric deadzoned unsharp + tone mapping.
    // Rolling rows keep peak temporary memory at O(width) instead of full-frame copies.
    const out = new Uint8ClampedArray(expectedLength);
    let prevBlur = new Float32Array(width);
    let currBlur = new Float32Array(width);
    let nextBlur = new Float32Array(width);

    blurRow1D(relLum, 0, width, currBlur);
    prevBlur.set(currBlur);
    if (height > 1) {
        blurRow1D(relLum, width, width, nextBlur);
    } else {
        nextBlur.set(currBlur);
    }

    if (filter === 'bw') {
        const bwWhite = 0.91;
        const bwBlack = 0.50;
        const invBwSpan = LUT_SIZE / (bwWhite - bwBlack);

        for (let y = 0; y < height; y++) {
            const row = y * width;

            for (let x = 0; x < width; x++) {
                const i = row + x;
                const o = i * 4;
                const blurred = (prevBlur[x] + 2 * currBlur[x] + nextBlur[x]) * 0.25;
                const sharpRel = sharpenRelativeLuminance(relLum[i], blurred);

                let value: number;
                if (sharpRel >= bwWhite) {
                    value = 255;
                } else if (sharpRel <= bwBlack) {
                    value = 0;
                } else {
                    const lutIdx = Math.round((sharpRel - bwBlack) * invBwSpan);
                    value = BW_GAMMA_LUT[lutIdx < 0 ? 0 : lutIdx > LUT_SIZE ? LUT_SIZE : lutIdx];
                }

                out[o] = value;
                out[o + 1] = value;
                out[o + 2] = value;
                out[o + 3] = data[o + 3];
            }

            if (y + 1 < height) {
                const recycled = prevBlur;
                prevBlur = currBlur;
                currBlur = nextBlur;
                nextBlur = recycled;
                if (y + 2 < height) {
                    blurRow1D(relLum, (y + 2) * width, width, nextBlur);
                } else {
                    nextBlur.set(currBlur);
                }
            }
        }

        return { width, height, data: out };
    }

    for (let y = 0; y < height; y++) {
        const row = y * width;
        const gy0 = y0Arr[y] * bgW;
        const gy1 = y1Arr[y] * bgW;
        const fy = fyArr[y];
        const invFy = 1 - fy;

        for (let x = 0; x < width; x++) {
            const i = row + x;
            const o = i * 4;
            const nLum = relLum[i];
            const blurred = (prevBlur[x] + 2 * currBlur[x] + nextBlur[x]) * 0.25;
            const sharpRel = sharpenRelativeLuminance(nLum, blurred);

            const gx0 = x0Arr[x];
            const gx1 = x1Arr[x];
            const fx = fxArr[x];
            const invFx = 1 - fx;

            const w00 = invFx * invFy;
            const w10 = fx * invFy;
            const w01 = invFx * fy;
            const w11 = fx * fy;

            const k00 = gy0 + gx0;
            const k10 = gy0 + gx1;
            const k01 = gy1 + gx0;
            const k11 = gy1 + gx1;

            const bgR = bgGridR[k00] * w00 + bgGridR[k10] * w10 + bgGridR[k01] * w01 + bgGridR[k11] * w11;
            const bgG = bgGridG[k00] * w00 + bgGridG[k10] * w10 + bgGridG[k01] * w01 + bgGridG[k11] * w11;
            const bgB = bgGridB[k00] * w00 + bgGridB[k10] * w10 + bgGridB[k01] * w01 + bgGridB[k11] * w11;

            let nr = data[o] / bgR;
            let ng = data[o + 1] / bgG;
            let nb = data[o + 2] / bgB;
            if (nr > 1.15) {
                nr = 1.15;
            }
            if (ng > 1.15) {
                ng = 1.15;
            }
            if (nb > 1.15) {
                nb = 1.15;
            }

            const maxCh = nr > ng ? (nr > nb ? nr : nb) : (ng > nb ? ng : nb);
            const minCh = nr < ng ? (nr < nb ? nr : nb) : (ng < nb ? ng : nb);
            const normChroma = maxCh - minCh;
            const chromaRatio = normChroma <= 0.06
                ? 0
                : normChroma >= 0.30
                    ? 1
                    : (normChroma - 0.06) / 0.24;

            const whitePoint = 0.935 + 0.055 * chromaRatio;
            if (sharpRel >= whitePoint && chromaRatio === 0) {
                out[o] = 255;
                out[o + 1] = 255;
                out[o + 2] = 255;
                out[o + 3] = data[o + 3];
                continue;
            }

            const blackPoint = 0.16 * (1 - 0.5 * chromaRatio);
            let targetRel: number;

            if (sharpRel >= whitePoint) {
                targetRel = 1;
            } else if (sharpRel <= blackPoint) {
                targetRel = sharpRel * 0.35;
            } else {
                const u = (sharpRel - blackPoint) / (whitePoint - blackPoint);
                const curve = chromaRatio === 0
                    ? DOC_NEUTRAL_GAMMA_LUT[Math.round(u * LUT_SIZE)]
                    : Math.pow(u, 1.85 - 0.65 * chromaRatio);
                const floorRel = blackPoint * 0.35;
                targetRel = floorRel + (1 - floorRel) * curve;
            }

            const targetLum = targetRel * 255;
            const paperClean = sharpRel <= 0.90
                ? 0
                : smoothstep01((sharpRel - 0.90) / 0.05) * (1 - chromaRatio);
            const satScale = (1 + 0.16 * chromaRatio) * (1 - paperClean) * 255;

            out[o] = clampByte(targetLum + (nr - nLum) * satScale);
            out[o + 1] = clampByte(targetLum + (ng - nLum) * satScale);
            out[o + 2] = clampByte(targetLum + (nb - nLum) * satScale);
            out[o + 3] = data[o + 3];
        }

        if (y + 1 < height) {
            const recycled = prevBlur;
            prevBlur = currBlur;
            currBlur = nextBlur;
            nextBlur = recycled;
            if (y + 2 < height) {
                blurRow1D(relLum, (y + 2) * width, width, nextBlur);
            } else {
                nextBlur.set(currBlur);
            }
        }
    }

    return { width, height, data: out };
}

export function normalizeRotation(degrees: number): QuarterTurnRotation {
    if (!Number.isFinite(degrees)) {
        return 0;
    }

    const quarterTurns = ((Math.round(degrees / 90) % 4) + 4) % 4;

    return (quarterTurns * 90) as QuarterTurnRotation;
}

export function rotateImageData(
    source: ImageDataLike,
    rotation: QuarterTurnRotation | number,
): ImageDataLike {
    const { width, height, data } = source;
    const expectedLength = width * height * 4;

    if (width <= 0 || height <= 0 || data.length < expectedLength) {
        throw new Error('Las dimensiones de la imagen para rotar no son válidas.');
    }

    const angle = normalizeRotation(rotation);

    if (angle === 0) {
        return {
            width,
            height,
            data: new Uint8ClampedArray(data.subarray(0, expectedLength)),
        };
    }

    const dstWidth = angle === 90 || angle === 270 ? height : width;
    const dstHeight = angle === 90 || angle === 270 ? width : height;
    const out = new Uint8ClampedArray(expectedLength);

    for (let sy = 0; sy < height; sy++) {
        for (let sx = 0; sx < width; sx++) {
            let dx: number;
            let dy: number;

            if (angle === 90) {
                dx = height - 1 - sy;
                dy = sx;
            } else if (angle === 180) {
                dx = width - 1 - sx;
                dy = height - 1 - sy;
            } else {
                dx = sy;
                dy = width - 1 - sx;
            }

            const srcIdx = (sy * width + sx) * 4;
            const dstIdx = (dy * dstWidth + dx) * 4;

            out[dstIdx] = data[srcIdx];
            out[dstIdx + 1] = data[srcIdx + 1];
            out[dstIdx + 2] = data[srcIdx + 2];
            out[dstIdx + 3] = data[srcIdx + 3];
        }
    }

    return {
        width: dstWidth,
        height: dstHeight,
        data: out,
    };
}

export function movePage<T>(pages: readonly T[], fromIndex: number, toIndex: number): T[] {
    if (
        !Number.isInteger(fromIndex)
        || !Number.isInteger(toIndex)
        || fromIndex < 0
        || toIndex < 0
        || fromIndex >= pages.length
        || toIndex >= pages.length
        || fromIndex === toIndex
    ) {
        return pages.slice();
    }

    const next = pages.slice();
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
}
