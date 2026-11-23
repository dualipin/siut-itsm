import { clamp01, clampByte } from './image-utils.ts';
import type { ImageDataLike } from './quad-detector.ts';

export type ScanFilter = 'documento' | 'bw' | 'original';

export type QuarterTurnRotation = 0 | 90 | 180 | 270;

export const DEFAULT_SCAN_FILTER: ScanFilter = 'documento';

function smoothstep01(t: number): number {
    const x = clamp01(t);

    return x * x * (3 - 2 * x);
}

function computeLocalBackgroundGrid(
    lum: Float32Array,
    width: number,
    height: number,
): { gridW: number; gridH: number; grid: Float32Array } {
    const hist = new Int32Array(256);
    const total = width * height;

    for (let i = 0; i < total; i++) {
        hist[clampByte(lum[i])]++;
    }

    const p90Target = Math.max(1, Math.floor(total * 0.9));
    let cumulative = 0;
    let globalPaperLum = 220;

    for (let bin = 0; bin < 256; bin++) {
        cumulative += hist[bin];
        if (cumulative >= p90Target) {
            globalPaperLum = bin;
            break;
        }
    }

    const cellSize = Math.max(8, Math.round(Math.max(width, height) / 20));
    const gridW = Math.max(1, Math.ceil(width / cellSize));
    const gridH = Math.max(1, Math.ceil(height / cellSize));
    const rawGrid = new Float32Array(gridW * gridH);
    const cellHist = new Int32Array(64);
    const floorLum = Math.max(96, globalPaperLum * 0.7);

    for (let gy = 0; gy < gridH; gy++) {
        const yStart = gy * cellSize;
        const yEnd = Math.min(height, yStart + cellSize);

        for (let gx = 0; gx < gridW; gx++) {
            const xStart = gx * cellSize;
            const xEnd = Math.min(width, xStart + cellSize);
            cellHist.fill(0);
            let cellCount = 0;

            for (let y = yStart; y < yEnd; y++) {
                const row = y * width;
                for (let x = xStart; x < xEnd; x++) {
                    const bin = Math.min(63, Math.max(0, (lum[row + x] * 0.25) | 0));
                    cellHist[bin]++;
                    cellCount++;
                }
            }

            const p85Target = Math.max(1, Math.floor(cellCount * 0.85));
            let acc = 0;
            let p85Lum = globalPaperLum;

            for (let bin = 0; bin < 64; bin++) {
                acc += cellHist[bin];
                if (acc >= p85Target) {
                    p85Lum = bin * 4 + 2;
                    break;
                }
            }

            rawGrid[gy * gridW + gx] = Math.max(floorLum, p85Lum);
        }
    }

    const smoothed = new Float32Array(gridW * gridH);
    for (let gy = 0; gy < gridH; gy++) {
        const y0 = Math.max(0, gy - 1);
        const y1 = Math.min(gridH - 1, gy + 1);

        for (let gx = 0; gx < gridW; gx++) {
            const x0 = Math.max(0, gx - 1);
            const x1 = Math.min(gridW - 1, gx + 1);
            let sum = 0;
            let count = 0;

            for (let ky = y0; ky <= y1; ky++) {
                for (let kx = x0; kx <= x1; kx++) {
                    sum += rawGrid[ky * gridW + kx];
                    count++;
                }
            }

            smoothed[gy * gridW + gx] = sum / count;
        }
    }

    return { gridW, gridH, grid: smoothed };
}

function sampleGridBilinear(
    grid: Float32Array,
    gridW: number,
    gridH: number,
    x: number,
    y: number,
    width: number,
    height: number,
): number {
    if (gridW === 1 && gridH === 1) {
        return grid[0];
    }

    const u = ((x + 0.5) / width) * gridW - 0.5;
    const v = ((y + 0.5) / height) * gridH - 0.5;
    const x0 = Math.max(0, Math.min(gridW - 1, Math.floor(u)));
    const y0 = Math.max(0, Math.min(gridH - 1, Math.floor(v)));
    const x1 = Math.min(gridW - 1, x0 + 1);
    const y1 = Math.min(gridH - 1, y0 + 1);
    const fx = clamp01(u - x0);
    const fy = clamp01(v - y0);

    return (
        grid[y0 * gridW + x0] * (1 - fx) * (1 - fy)
        + grid[y0 * gridW + x1] * fx * (1 - fy)
        + grid[y1 * gridW + x0] * (1 - fx) * fy
        + grid[y1 * gridW + x1] * fx * fy
    );
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
    const lum = new Float32Array(total);
    for (let i = 0; i < total; i++) {
        const o = i * 4;
        lum[i] = 0.299 * data[o] + 0.587 * data[o + 1] + 0.114 * data[o + 2];
    }

    const { gridW, gridH, grid } = computeLocalBackgroundGrid(lum, width, height);
    const out = new Uint8ClampedArray(expectedLength);

    if (filter === 'bw') {
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const i = y * width + x;
                const o = i * 4;
                const localBg = sampleGridBilinear(grid, gridW, gridH, x, y, width, height);
                const rel = lum[i] / Math.max(1, localBg);

                let value: number;
                if (rel >= 0.78) {
                    value = 255;
                } else if (rel <= 0.42) {
                    value = 0;
                } else {
                    value = Math.round(255 * smoothstep01((rel - 0.42) / 0.36));
                }

                out[o] = value;
                out[o + 1] = value;
                out[o + 2] = value;
                out[o + 3] = data[o + 3];
            }
        }

        return { width, height, data: out };
    }

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const i = y * width + x;
            const o = i * 4;
            const r = data[o];
            const g = data[o + 1];
            const b = data[o + 2];
            const yLum = lum[i];

            const maxCh = Math.max(r, g, b);
            const minCh = Math.min(r, g, b);
            const chroma = maxCh - minCh;
            const chromaRatio = clamp01(chroma / 60);

            const localBg = sampleGridBilinear(grid, gridW, gridH, x, y, width, height);
            const rel = yLum / Math.max(1, localBg);

            const whitePoint = 0.80 + 0.14 * chromaRatio;
            const blackPoint = 0.18 - 0.10 * chromaRatio;

            let targetRel: number;
            if (rel >= whitePoint) {
                targetRel = 1;
            } else if (rel <= blackPoint) {
                targetRel = rel * 0.45;
            } else {
                const u = (rel - blackPoint) / (whitePoint - blackPoint);
                const curve = smoothstep01(u);
                const floorRel = blackPoint * 0.45;
                targetRel = floorRel + (1 - floorRel) * curve;
            }

            const targetLum = targetRel * 255;
            const paperClean = smoothstep01((rel - 0.76) / 0.10) * (1 - chromaRatio);
            const satScale = (1 + 0.18 * chromaRatio) * (1 - paperClean);

            out[o] = clampByte(targetLum + (r - yLum) * satScale);
            out[o + 1] = clampByte(targetLum + (g - yLum) * satScale);
            out[o + 2] = clampByte(targetLum + (b - yLum) * satScale);
            out[o + 3] = data[o + 3];
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
