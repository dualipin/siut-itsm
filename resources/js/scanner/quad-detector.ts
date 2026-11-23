import { clamp01 } from './image-utils.ts';

export interface Point2D {
    x: number;
    y: number;
}

export interface DocumentQuad {
    topLeft: Point2D;
    topRight: Point2D;
    bottomRight: Point2D;
    bottomLeft: Point2D;
}

export interface ImageDataLike {
    width: number;
    height: number;
    data: Uint8ClampedArray | Uint8Array;
}

export interface QuadDetectionResult {
    quad: DocumentQuad;
    detected: boolean;
    confidence: number;
}

export const FULL_DOCUMENT_QUAD: DocumentQuad = {
    topLeft: { x: 0, y: 0 },
    topRight: { x: 1, y: 0 },
    bottomRight: { x: 1, y: 1 },
    bottomLeft: { x: 0, y: 1 },
};

const MAX_WORKING_SIDE = 320;
const MIN_CONTRAST_DELTA = 20;
const MIN_AREA_RATIO = 0.10;
const MAX_AREA_RATIO = 0.985;
const MIN_CORNER_ANGLE_DEG = 42;
const MAX_CORNER_ANGLE_DEG = 138;
const CHROMA_WEIGHT = 2.4;

interface WorkingChannels {
    width: number;
    height: number;
    lum: Float32Array;
    cb: Float32Array;
    cr: Float32Array;
}

interface SobelGradients {
    gxLum: Float32Array;
    gyLum: Float32Array;
    gxCb: Float32Array;
    gyCb: Float32Array;
    gxCr: Float32Array;
    gyCr: Float32Array;
}

interface Line2D {
    nx: number;
    ny: number;
    c: number;
}

interface QuadEdgeMetrics {
    meanContrast: number;
    minInsetContrast: number;
    supportRatio: number;
    insetSides: number;
}

interface CandidateQuad {
    quad: DocumentQuad;
    score: number;
    metrics: QuadEdgeMetrics;
}

export function cloneQuad(quad: DocumentQuad): DocumentQuad {
    return {
        topLeft: { x: quad.topLeft.x, y: quad.topLeft.y },
        topRight: { x: quad.topRight.x, y: quad.topRight.y },
        bottomRight: { x: quad.bottomRight.x, y: quad.bottomRight.y },
        bottomLeft: { x: quad.bottomLeft.x, y: quad.bottomLeft.y },
    };
}

export function quadToArray(quad: DocumentQuad): [Point2D, Point2D, Point2D, Point2D] {
    return [quad.topLeft, quad.topRight, quad.bottomRight, quad.bottomLeft];
}

export function isValidConvexQuad(quad: DocumentQuad): boolean {
    const pts = quadToArray(quad);

    for (const pt of pts) {
        if (!Number.isFinite(pt.x) || !Number.isFinite(pt.y)) {
            return false;
        }
    }

    let sign = 0;
    let area2 = 0;

    for (let i = 0; i < 4; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % 4];
        const c = pts[(i + 2) % 4];

        const crossVal = (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x);
        if (Math.abs(crossVal) < 1e-6) {
            return false;
        }

        const currentSign = crossVal > 0 ? 1 : -1;
        if (sign === 0) {
            sign = currentSign;
        } else if (sign !== currentSign) {
            return false;
        }

        area2 += a.x * b.y - b.x * a.y;
    }

    return Math.abs(area2) * 0.5 > 1e-4;
}

function fallbackResult(): QuadDetectionResult {
    return {
        quad: cloneQuad(FULL_DOCUMENT_QUAD),
        detected: false,
        confidence: 0,
    };
}

function downsampleChannels(imageData: ImageDataLike): WorkingChannels {
    const { width: srcW, height: srcH, data } = imageData;
    const longSide = Math.max(srcW, srcH);

    if (longSide <= MAX_WORKING_SIDE) {
        const total = srcW * srcH;
        const lum = new Float32Array(total);
        const cb = new Float32Array(total);
        const cr = new Float32Array(total);

        for (let i = 0; i < total; i++) {
            const o = i * 4;
            const r = data[o];
            const g = data[o + 1];
            const b = data[o + 2];
            const y = 0.299 * r + 0.587 * g + 0.114 * b;
            lum[i] = y;
            cb[i] = (b - y) * 0.564;
            cr[i] = (r - y) * 0.713;
        }

        return { width: srcW, height: srcH, lum, cb, cr };
    }

    const scale = MAX_WORKING_SIDE / longSide;
    const dstW = Math.max(16, Math.round(srcW * scale));
    const dstH = Math.max(16, Math.round(srcH * scale));
    const total = dstW * dstH;
    const lum = new Float32Array(total);
    const cb = new Float32Array(total);
    const cr = new Float32Array(total);

    for (let y = 0; y < dstH; y++) {
        const sy0 = Math.floor((y * srcH) / dstH);
        const sy1 = Math.max(sy0 + 1, Math.min(srcH, Math.ceil(((y + 1) * srcH) / dstH)));
        const yStep = Math.max(1, Math.floor((sy1 - sy0) / 3));

        for (let x = 0; x < dstW; x++) {
            const sx0 = Math.floor((x * srcW) / dstW);
            const sx1 = Math.max(sx0 + 1, Math.min(srcW, Math.ceil(((x + 1) * srcW) / dstW)));
            const xStep = Math.max(1, Math.floor((sx1 - sx0) / 3));

            let sumR = 0;
            let sumG = 0;
            let sumB = 0;
            let count = 0;

            for (let sy = sy0; sy < sy1; sy += yStep) {
                const rowOffset = sy * srcW;
                for (let sx = sx0; sx < sx1; sx += xStep) {
                    const o = (rowOffset + sx) * 4;
                    sumR += data[o];
                    sumG += data[o + 1];
                    sumB += data[o + 2];
                    count++;
                }
            }

            const inv = 1 / Math.max(1, count);
            const r = sumR * inv;
            const g = sumG * inv;
            const b = sumB * inv;
            const yVal = 0.299 * r + 0.587 * g + 0.114 * b;
            const idx = y * dstW + x;

            lum[idx] = yVal;
            cb[idx] = (b - yVal) * 0.564;
            cr[idx] = (r - yVal) * 0.713;
        }
    }

    return { width: dstW, height: dstH, lum, cb, cr };
}

function gaussianBlur3x3(src: Float32Array, width: number, height: number): Float32Array {
    const tmp = new Float32Array(width * height);
    const dst = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const xm1 = x > 0 ? x - 1 : 0;
            const xp1 = x + 1 < width ? x + 1 : width - 1;
            tmp[row + x] = (src[row + xm1] + 2 * src[row + x] + src[row + xp1]) * 0.25;
        }
    }

    for (let y = 0; y < height; y++) {
        const ym1 = y > 0 ? y - 1 : 0;
        const yp1 = y + 1 < height ? y + 1 : height - 1;
        const r0 = ym1 * width;
        const r1 = y * width;
        const r2 = yp1 * width;
        for (let x = 0; x < width; x++) {
            dst[r1 + x] = (tmp[r0 + x] + 2 * tmp[r1 + x] + tmp[r2 + x]) * 0.25;
        }
    }

    return dst;
}

function computeSobelChannel(
    src: Float32Array,
    width: number,
    height: number,
): { gx: Float32Array; gy: Float32Array } {
    const gx = new Float32Array(width * height);
    const gy = new Float32Array(width * height);

    for (let y = 1; y < height - 1; y++) {
        const r0 = (y - 1) * width;
        const r1 = y * width;
        const r2 = (y + 1) * width;

        for (let x = 1; x < width - 1; x++) {
            const tl = src[r0 + x - 1];
            const tc = src[r0 + x];
            const tr = src[r0 + x + 1];
            const ml = src[r1 + x - 1];
            const mr = src[r1 + x + 1];
            const bl = src[r2 + x - 1];
            const bc = src[r2 + x];
            const br = src[r2 + x + 1];

            gx[r1 + x] = (tr + 2 * mr + br - tl - 2 * ml - bl) * 0.25;
            gy[r1 + x] = (bl + 2 * bc + br - tl - 2 * tc - tr) * 0.25;
        }
    }

    return { gx, gy };
}

function computeSobelGradients(ch: WorkingChannels): SobelGradients {
    const sLum = computeSobelChannel(ch.lum, ch.width, ch.height);
    const sCb = computeSobelChannel(ch.cb, ch.width, ch.height);
    const sCr = computeSobelChannel(ch.cr, ch.width, ch.height);

    return {
        gxLum: sLum.gx,
        gyLum: sLum.gy,
        gxCb: sCb.gx,
        gyCb: sCb.gy,
        gxCr: sCr.gx,
        gyCr: sCr.gy,
    };
}

function computeOtsuThreshold(values: Float32Array): { threshold: number; contrastDelta: number } {
    const hist = new Int32Array(256);
    const total = values.length;
    let totalSum = 0;

    for (let i = 0; i < total; i++) {
        const bin = Math.max(0, Math.min(255, Math.round(values[i])));
        hist[bin]++;
        totalSum += bin;
    }

    let sumB = 0;
    let weightB = 0;
    let maxVariance = -1;
    let bestThreshold = 128;
    let bestDelta = 0;

    for (let t = 0; t < 256; t++) {
        weightB += hist[t];
        if (weightB === 0) {
            continue;
        }

        const weightF = total - weightB;
        if (weightF === 0) {
            break;
        }

        sumB += t * hist[t];
        const meanB = sumB / weightB;
        const meanF = (totalSum - sumB) / weightF;
        const diff = meanF - meanB;
        const variance = weightB * weightF * diff * diff;

        if (variance > maxVariance) {
            maxVariance = variance;
            bestThreshold = t;
            bestDelta = Math.abs(diff);
        }
    }

    return { threshold: bestThreshold, contrastDelta: bestDelta };
}

function buildOtsuForegroundMask(
    values: Float32Array,
    width: number,
    height: number,
    threshold: number,
): Uint8Array | null {
    let borderHigh = 0;
    let borderTotal = 0;
    const borderThickness = Math.max(1, Math.min(3, Math.floor(Math.min(width, height) * 0.015)));

    for (let y = 0; y < height; y++) {
        const isBorderRow = y < borderThickness || y >= height - borderThickness;
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const isBorderCol = x < borderThickness || x >= width - borderThickness;
            if (isBorderRow || isBorderCol) {
                borderTotal++;
                if (values[row + x] >= threshold) {
                    borderHigh++;
                }
            }
        }
    }

    let centerHigh = 0;
    let centerTotal = 0;
    const cx0 = Math.floor(width * 0.24);
    const cx1 = Math.ceil(width * 0.76);
    const cy0 = Math.floor(height * 0.24);
    const cy1 = Math.ceil(height * 0.76);

    for (let y = cy0; y < cy1; y++) {
        const row = y * width;
        for (let x = cx0; x < cx1; x++) {
            centerTotal++;
            if (values[row + x] >= threshold) {
                centerHigh++;
            }
        }
    }

    const pBorderHigh = borderHigh / Math.max(1, borderTotal);
    const pCenterHigh = centerHigh / Math.max(1, centerTotal);

    let brightForeground: boolean;
    if (pCenterHigh - pBorderHigh >= 0.08) {
        brightForeground = true;
    } else if (pBorderHigh - pCenterHigh >= 0.08) {
        brightForeground = false;
    } else {
        return null;
    }

    const borderFgRatio = brightForeground ? pBorderHigh : 1 - pBorderHigh;
    if (borderFgRatio > 0.88) {
        return null;
    }

    const mask = new Uint8Array(width * height);
    for (let i = 0; i < values.length; i++) {
        const isHigh = values[i] >= threshold;
        mask[i] = isHigh === brightForeground ? 1 : 0;
    }

    return mask;
}

function medianOf(arr: number[]): number {
    if (arr.length === 0) {
        return 0;
    }

    arr.sort((a, b) => a - b);
    const mid = arr.length >> 1;

    return arr.length % 2 === 1 ? arr[mid] : (arr[mid - 1] + arr[mid]) * 0.5;
}

function buildColorDistanceMask(ch: WorkingChannels): Uint8Array | null {
    const { width, height, lum, cb, cr } = ch;
    const borderThickness = Math.max(2, Math.min(4, Math.round(Math.min(width, height) * 0.018)));
    const borderY: number[] = [];
    const borderCb: number[] = [];
    const borderCr: number[] = [];

    for (let y = 0; y < height; y++) {
        const isBorderRow = y < borderThickness || y >= height - borderThickness;
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const isBorderCol = x < borderThickness || x >= width - borderThickness;
            if (isBorderRow || isBorderCol) {
                const idx = row + x;
                borderY.push(lum[idx]);
                borderCb.push(cb[idx]);
                borderCr.push(cr[idx]);
            }
        }
    }

    const bgY = medianOf(borderY);
    const bgCb = medianOf(borderCb);
    const bgCr = medianOf(borderCr);

    const cx0 = Math.floor(width * 0.22);
    const cx1 = Math.ceil(width * 0.78);
    const cy0 = Math.floor(height * 0.22);
    const cy1 = Math.ceil(height * 0.78);
    const centerEntries: Array<{ y: number; cb: number; cr: number; dist: number }> = [];

    for (let y = cy0; y < cy1; y += 2) {
        const row = y * width;
        for (let x = cx0; x < cx1; x += 2) {
            const idx = row + x;
            const dY = lum[idx] - bgY;
            const dCb = (cb[idx] - bgCb) * CHROMA_WEIGHT;
            const dCr = (cr[idx] - bgCr) * CHROMA_WEIGHT;
            centerEntries.push({
                y: lum[idx],
                cb: cb[idx],
                cr: cr[idx],
                dist: Math.hypot(dY, dCb, dCr),
            });
        }
    }

    if (centerEntries.length < 16) {
        return null;
    }

    // Prefer the upper half of center pixels most distinct from the border (ignores interior dark text)
    centerEntries.sort((a, b) => b.dist - a.dist);
    const topCount = Math.max(8, Math.floor(centerEntries.length * 0.45));
    const fgYList: number[] = [];
    const fgCbList: number[] = [];
    const fgCrList: number[] = [];

    for (let i = 0; i < topCount; i++) {
        fgYList.push(centerEntries[i].y);
        fgCbList.push(centerEntries[i].cb);
        fgCrList.push(centerEntries[i].cr);
    }

    const fgY = medianOf(fgYList);
    const fgCb = medianOf(fgCbList);
    const fgCr = medianOf(fgCrList);

    const colorSep = Math.hypot(fgY - bgY, (fgCb - bgCb) * CHROMA_WEIGHT, (fgCr - bgCr) * CHROMA_WEIGHT);
    if (colorSep < 20) {
        return null;
    }

    const chromaSep = Math.hypot((fgCb - bgCb) * CHROMA_WEIGHT, (fgCr - bgCr) * CHROMA_WEIGHT);
    // Weight luminance slightly lower when chroma cleanly separates paper from wood/colored desk so shadows stay inside paper.
    const lumWeight = chromaSep >= 18 ? 0.55 : 0.85;
    const total = width * height;
    const score = new Float32Array(total);

    for (let i = 0; i < total; i++) {
        const dBg = Math.hypot(
            (lum[i] - bgY) * lumWeight,
            (cb[i] - bgCb) * CHROMA_WEIGHT,
            (cr[i] - bgCr) * CHROMA_WEIGHT,
        );
        const dFg = Math.hypot(
            (lum[i] - fgY) * lumWeight,
            (cb[i] - fgCb) * CHROMA_WEIGHT,
            (cr[i] - fgCr) * CHROMA_WEIGHT,
        );
        const raw = 128 + ((dBg - dFg) / Math.max(20, colorSep)) * 115;
        score[i] = Math.max(0, Math.min(255, raw));
    }

    const { threshold, contrastDelta } = computeOtsuThreshold(score);
    if (contrastDelta < 18) {
        return null;
    }

    return buildOtsuForegroundMask(score, width, height, threshold);
}

function buildShadowCompensatedMask(ch: WorkingChannels): Uint8Array | null {
    const { width, height, lum } = ch;
    const gridN = 6;
    const cellW = width / gridN;
    const cellH = height / gridN;

    // Fit a linear 2D illumination plane a*x + b*y + c to local bright-envelope (88th percentile) per cell.
    // This removes strong directional phone-shadow gradients across the page without erasing the paper-to-desk step.
    let sxx = 0;
    let syy = 0;
    let sxy = 0;
    let sx = 0;
    let sy = 0;
    let sn = 0;
    let sz = 0;
    let sxz = 0;
    let syz = 0;

    const samples: number[] = [];

    for (let gy = 0; gy < gridN; gy++) {
        const y0 = Math.floor(gy * cellH);
        const y1 = Math.min(height, Math.ceil((gy + 1) * cellH));

        for (let gx = 0; gx < gridN; gx++) {
            const x0 = Math.floor(gx * cellW);
            const x1 = Math.min(width, Math.ceil((gx + 1) * cellW));

            samples.length = 0;
            for (let y = y0; y < y1; y += 2) {
                const row = y * width;
                for (let x = x0; x < x1; x += 2) {
                    samples.push(lum[row + x]);
                }
            }

            if (samples.length < 4) {
                continue;
            }

            samples.sort((a, b) => a - b);
            const p88 = samples[Math.min(samples.length - 1, Math.floor(samples.length * 0.88))];
            const cx = ((x0 + x1) * 0.5) / width;
            const cy = ((y0 + y1) * 0.5) / height;

            sxx += cx * cx;
            syy += cy * cy;
            sxy += cx * cy;
            sx += cx;
            sy += cy;
            sn += 1;
            sz += p88;
            sxz += cx * p88;
            syz += cy * p88;
        }
    }

    if (sn < 6) {
        return null;
    }

    // Solve 3x3 normal equations for (a, b, c)
    const m = [
        [sxx, sxy, sx, sxz],
        [sxy, syy, sy, syz],
        [sx, sy, sn, sz],
    ];

    for (let col = 0; col < 3; col++) {
        let pivot = col;
        for (let row = col + 1; row < 3; row++) {
            if (Math.abs(m[row][col]) > Math.abs(m[pivot][col])) {
                pivot = row;
            }
        }
        if (Math.abs(m[pivot][col]) < 1e-8) {
            return null;
        }
        if (pivot !== col) {
            const tmp = m[col];
            m[col] = m[pivot];
            m[pivot] = tmp;
        }
        const div = m[col][col];
        for (let j = col; j <= 3; j++) {
            m[col][j] /= div;
        }
        for (let row = 0; row < 3; row++) {
            if (row === col) {
                continue;
            }
            const factor = m[row][col];
            for (let j = col; j <= 3; j++) {
                m[row][j] -= factor * m[col][j];
            }
        }
    }

    const a = m[0][3];
    const b = m[1][3];
    const c = m[2][3];

    // Only useful when there is an actual illumination slope across the image.
    if (Math.hypot(a, b) < 15) {
        return null;
    }

    const normalized = new Float32Array(width * height);
    for (let y = 0; y < height; y++) {
        const ny = y / height;
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const nx = x / width;
            const plane = Math.max(45, a * nx + b * ny + c);
            normalized[row + x] = Math.max(0, Math.min(255, (lum[row + x] / plane) * 220));
        }
    }

    const { threshold, contrastDelta } = computeOtsuThreshold(normalized);
    if (contrastDelta < MIN_CONTRAST_DELTA) {
        return null;
    }

    return buildOtsuForegroundMask(normalized, width, height, threshold);
}

function morphCloseMask(mask: Uint8Array, width: number, height: number, radius: number): Uint8Array {
    if (radius <= 0) {
        return mask;
    }

    const total = width * height;
    const dilatedX = new Uint8Array(total);
    const dilated = new Uint8Array(total);
    const erodedX = new Uint8Array(total);
    const closed = new Uint8Array(total);

    // Dilate X
    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - radius);
            const x1 = Math.min(width - 1, x + radius);
            let on = 0;
            for (let kx = x0; kx <= x1; kx++) {
                if (mask[row + kx] === 1) {
                    on = 1;
                    break;
                }
            }
            dilatedX[row + x] = on;
        }
    }

    // Dilate Y
    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - radius);
        const y1 = Math.min(height - 1, y + radius);
        const row = y * width;
        for (let x = 0; x < width; x++) {
            let on = 0;
            for (let ky = y0; ky <= y1; ky++) {
                if (dilatedX[ky * width + x] === 1) {
                    on = 1;
                    break;
                }
            }
            dilated[row + x] = on;
        }
    }

    // Erode X (out-of-bounds treated as 0 so edges don't stick artificially to canvas border)
    for (let y = 0; y < height; y++) {
        const row = y * width;
        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - radius);
            const x1 = Math.min(width - 1, x + radius);
            let allOn = 1;
            for (let kx = x0; kx <= x1; kx++) {
                if (dilated[row + kx] === 0) {
                    allOn = 0;
                    break;
                }
            }
            erodedX[row + x] = allOn;
        }
    }

    // Erode Y
    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - radius);
        const y1 = Math.min(height - 1, y + radius);
        const row = y * width;
        for (let x = 0; x < width; x++) {
            let allOn = 1;
            for (let ky = y0; ky <= y1; ky++) {
                if (erodedX[ky * width + x] === 0) {
                    allOn = 0;
                    break;
                }
            }
            closed[row + x] = allOn;
        }
    }

    return closed;
}

function extractLargestComponentBoundary(
    mask: Uint8Array,
    width: number,
    height: number,
): { count: number; boundary: Point2D[] } | null {
    const total = width * height;
    const visited = new Uint8Array(total);
    const queue = new Int32Array(total);
    const currentComponent = new Int32Array(total);

    let bestCount = 0;
    let bestComponent = new Int32Array(0);

    for (let start = 0; start < total; start++) {
        if (mask[start] === 0 || visited[start] === 1) {
            continue;
        }

        let head = 0;
        let tail = 0;
        let count = 0;
        visited[start] = 1;
        queue[tail++] = start;

        while (head < tail) {
            const idx = queue[head++];
            currentComponent[count++] = idx;

            const x = idx % width;
            const y = (idx - x) / width;

            if (x > 0) {
                const n = idx - 1;
                if (mask[n] === 1 && visited[n] === 0) {
                    visited[n] = 1;
                    queue[tail++] = n;
                }
            }
            if (x + 1 < width) {
                const n = idx + 1;
                if (mask[n] === 1 && visited[n] === 0) {
                    visited[n] = 1;
                    queue[tail++] = n;
                }
            }
            if (y > 0) {
                const n = idx - width;
                if (mask[n] === 1 && visited[n] === 0) {
                    visited[n] = 1;
                    queue[tail++] = n;
                }
            }
            if (y + 1 < height) {
                const n = idx + width;
                if (mask[n] === 1 && visited[n] === 0) {
                    visited[n] = 1;
                    queue[tail++] = n;
                }
            }
        }

        if (count > bestCount) {
            bestCount = count;
            bestComponent = currentComponent.slice(0, count);
        }
    }

    const areaRatio = bestCount / total;
    if (areaRatio < MIN_AREA_RATIO || areaRatio > MAX_AREA_RATIO) {
        return null;
    }

    const componentMask = new Uint8Array(total);
    for (let i = 0; i < bestCount; i++) {
        componentMask[bestComponent[i]] = 1;
    }

    const boundary: Point2D[] = [];
    for (let i = 0; i < bestCount; i++) {
        const idx = bestComponent[i];
        const x = idx % width;
        const y = (idx - x) / width;

        const isEdge = x === 0
            || y === 0
            || x === width - 1
            || y === height - 1
            || componentMask[idx - 1] === 0
            || componentMask[idx + 1] === 0
            || componentMask[idx - width] === 0
            || componentMask[idx + width] === 0;

        if (isEdge) {
            boundary.push({ x, y });
        }
    }

    return { count: bestCount, boundary };
}

function cross(o: Point2D, a: Point2D, b: Point2D): number {
    return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
}

function convexHull(points: Point2D[]): Point2D[] {
    if (points.length < 3) {
        return points.slice();
    }

    const sorted = points
        .slice()
        .sort((a, b) => (a.x !== b.x ? a.x - b.x : a.y - b.y));

    const lower: Point2D[] = [];
    for (const p of sorted) {
        while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], p) <= 0) {
            lower.pop();
        }
        lower.push(p);
    }

    const upper: Point2D[] = [];
    for (let i = sorted.length - 1; i >= 0; i--) {
        const p = sorted[i];
        while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], p) <= 0) {
            upper.pop();
        }
        upper.push(p);
    }

    lower.pop();
    upper.pop();

    return lower.concat(upper);
}

function reduceHullToQuad(hull: Point2D[]): Point2D[] | null {
    if (hull.length < 4) {
        return null;
    }

    const pts = hull.slice();
    while (pts.length > 4) {
        let minIdx = 0;
        let minArea = Infinity;

        for (let i = 0; i < pts.length; i++) {
            const prev = pts[(i - 1 + pts.length) % pts.length];
            const curr = pts[i];
            const next = pts[(i + 1) % pts.length];
            const area = Math.abs(cross(prev, curr, next)) * 0.5;

            if (area < minArea) {
                minArea = area;
                minIdx = i;
            }
        }

        pts.splice(minIdx, 1);
    }

    return pts;
}

function fitWeightedLineIRLS(points: ReadonlyArray<Point2D>, initialWeights?: ReadonlyArray<number>): Line2D | null {
    const n = points.length;
    if (n < 4) {
        return null;
    }

    const weights = new Float64Array(n);
    for (let i = 0; i < n; i++) {
        weights[i] = initialWeights ? Math.max(0.05, initialWeights[i]) : 1;
    }

    let line: Line2D | null = null;
    const huberDelta = 1.5;

    for (let iter = 0; iter < 3; iter++) {
        let wSum = 0;
        let meanX = 0;
        let meanY = 0;

        for (let i = 0; i < n; i++) {
            const w = weights[i];
            wSum += w;
            meanX += w * points[i].x;
            meanY += w * points[i].y;
        }

        if (wSum <= 1e-6) {
            return null;
        }

        meanX /= wSum;
        meanY /= wSum;

        let sxx = 0;
        let syy = 0;
        let sxy = 0;

        for (let i = 0; i < n; i++) {
            const w = weights[i];
            const px = points[i].x - meanX;
            const py = points[i].y - meanY;
            sxx += w * px * px;
            syy += w * py * py;
            sxy += w * px * py;
        }

        const theta = 0.5 * Math.atan2(2 * sxy, sxx - syy);
        const nx = -Math.sin(theta);
        const ny = Math.cos(theta);
        const c = nx * meanX + ny * meanY;
        line = { nx, ny, c };

        for (let i = 0; i < n; i++) {
            const baseW = initialWeights ? Math.max(0.05, initialWeights[i]) : 1;
            const residual = Math.abs(nx * points[i].x + ny * points[i].y - c);
            weights[i] = residual <= huberDelta ? baseW : baseW * (huberDelta / residual);
        }
    }

    return line;
}

function fitEdgeLine(points: Point2D[], a: Point2D, b: Point2D): Line2D | null {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const len = Math.hypot(dx, dy);
    if (len < 8) {
        return null;
    }

    const ux = dx / len;
    const uy = dy / len;
    const perpLimit = Math.max(2.5, Math.min(6, len * 0.03));
    const support: Point2D[] = [];

    for (const p of points) {
        const vx = p.x - a.x;
        const vy = p.y - a.y;
        const proj = vx * ux + vy * uy;

        if (proj < len * 0.08 || proj > len * 0.92) {
            continue;
        }

        const perp = Math.abs(vx * -uy + vy * ux);
        if (perp <= perpLimit) {
            support.push(p);
        }
    }

    return fitWeightedLineIRLS(support);
}

function intersectLines(l1: Line2D, l2: Line2D): Point2D | null {
    const det = l1.nx * l2.ny - l2.nx * l1.ny;
    if (Math.abs(det) < 1e-3) {
        return null;
    }

    return {
        x: (l1.c * l2.ny - l2.c * l1.ny) / det,
        y: (l1.nx * l2.c - l2.nx * l1.c) / det,
    };
}

function refineQuadWithLineFit(
    corners: Point2D[],
    boundary: Point2D[],
    width: number,
    height: number,
): Point2D[] {
    const lines = corners.map((a, i) => fitEdgeLine(boundary, a, corners[(i + 1) % 4]));
    const refined: Point2D[] = [];

    for (let i = 0; i < 4; i++) {
        const prevLine = lines[(i + 3) % 4];
        const currLine = lines[i];
        const fallback = corners[i];

        if (!prevLine || !currLine) {
            refined.push(fallback);
            continue;
        }

        const hit = intersectLines(prevLine, currLine);
        if (
            !hit
            || hit.x < -0.05 * width
            || hit.x > 1.05 * width
            || hit.y < -0.05 * height
            || hit.y > 1.05 * height
            || Math.hypot(hit.x - fallback.x, hit.y - fallback.y) > Math.max(width, height) * 0.09
        ) {
            refined.push(fallback);
            continue;
        }

        refined.push({
            x: Math.max(0, Math.min(width - 1, hit.x)),
            y: Math.max(0, Math.min(height - 1, hit.y)),
        });
    }

    return refined;
}

function sampleFieldBilinear(field: Float32Array, width: number, height: number, x: number, y: number): number {
    const cx = Math.max(0, Math.min(width - 1, x));
    const cy = Math.max(0, Math.min(height - 1, y));
    const x0 = Math.floor(cx);
    const y0 = Math.floor(cy);
    const x1 = Math.min(width - 1, x0 + 1);
    const y1 = Math.min(height - 1, y0 + 1);
    const fx = cx - x0;
    const fy = cy - y0;

    return (
        field[y0 * width + x0] * (1 - fx) * (1 - fy)
        + field[y0 * width + x1] * fx * (1 - fy)
        + field[y1 * width + x0] * (1 - fx) * fy
        + field[y1 * width + x1] * fx * fy
    );
}

function snapQuadToGradients(
    quad: DocumentQuad,
    ch: WorkingChannels,
    grads: SobelGradients,
): DocumentQuad {
    const { width, height } = ch;
    const pts = quadToArray(quad);
    const searchRadius = Math.max(4, Math.min(8, Math.round(Math.min(width, height) * 0.025)));
    const step = 0.5;
    const numStations = 26;
    const lines: Array<Line2D | null> = [];

    for (let i = 0; i < 4; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % 4];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const len = Math.hypot(dx, dy);

        if (len < 12) {
            lines.push(null);
            continue;
        }

        // Inward normal for clockwise quad in screen coordinates (+y down)
        const inX = -dy / len;
        const inY = dx / len;

        // Check if this edge sits right on the canvas border; if so, keep the border line as-is
        const midX = (a.x + b.x) * 0.5;
        const midY = (a.y + b.y) * 0.5;
        if (midX < 1.5 || midX > width - 2.5 || midY < 1.5 || midY > height - 2.5) {
            lines.push(null);
            continue;
        }

        const peakPoints: Point2D[] = [];
        const peakWeights: number[] = [];

        for (let s = 0; s < numStations; s++) {
            const t = 0.10 + (0.80 * s) / (numStations - 1);
            const sx = a.x + dx * t;
            const sy = a.y + dy * t;

            let bestScore = 0;
            let bestRaw = 0;
            let bestD = 0;

            for (let d = -searchRadius; d <= searchRadius; d += step) {
                const px = sx + inX * d;
                const py = sy + inY * d;

                if (px < 1 || px > width - 2 || py < 1 || py > height - 2) {
                    continue;
                }

                const dLum = sampleFieldBilinear(grads.gxLum, width, height, px, py) * inX
                    + sampleFieldBilinear(grads.gyLum, width, height, px, py) * inY;
                const dCb = sampleFieldBilinear(grads.gxCb, width, height, px, py) * inX
                    + sampleFieldBilinear(grads.gyCb, width, height, px, py) * inY;
                const dCr = sampleFieldBilinear(grads.gxCr, width, height, px, py) * inX
                    + sampleFieldBilinear(grads.gyCr, width, height, px, py) * inY;

                const mag = Math.hypot(dLum, dCb * CHROMA_WEIGHT, dCr * CHROMA_WEIGHT);
                // Mild proximity preference keeps the edge locked to the outer paper boundary
                const proximity = 1 / (1 + (d * d) / (searchRadius * searchRadius * 1.4));
                const score = mag * proximity;

                if (score > bestScore) {
                    bestScore = score;
                    bestRaw = mag;
                    bestD = d;
                }
            }

            if (bestRaw >= 10) {
                peakPoints.push({
                    x: sx + inX * bestD,
                    y: sy + inY * bestD,
                });
                peakWeights.push(Math.min(4, bestRaw / 15));
            }
        }

        if (peakPoints.length >= 10) {
            lines.push(fitWeightedLineIRLS(peakPoints, peakWeights));
        } else {
            lines.push(null);
        }
    }

    const snappedPts: Point2D[] = [];
    const maxShift = Math.max(width, height) * 0.055;

    for (let i = 0; i < 4; i++) {
        const prevLine = lines[(i + 3) % 4];
        const currLine = lines[i];
        const fallback = pts[i];

        if (!prevLine || !currLine) {
            snappedPts.push(fallback);
            continue;
        }

        const hit = intersectLines(prevLine, currLine);
        if (
            !hit
            || hit.x < -0.02 * width
            || hit.x > 1.02 * width
            || hit.y < -0.02 * height
            || hit.y > 1.02 * height
            || Math.hypot(hit.x - fallback.x, hit.y - fallback.y) > maxShift
        ) {
            snappedPts.push(fallback);
            continue;
        }

        snappedPts.push({
            x: Math.max(0, Math.min(width - 1, hit.x)),
            y: Math.max(0, Math.min(height - 1, hit.y)),
        });
    }

    const snappedQuad = orderCorners(snappedPts);

    return isValidConvexQuad(snappedQuad) ? snappedQuad : quad;
}

function orderCorners(corners: Point2D[]): DocumentQuad {
    let cx = 0;
    let cy = 0;
    for (const p of corners) {
        cx += p.x;
        cy += p.y;
    }
    cx /= 4;
    cy /= 4;

    const clockwise = corners
        .slice()
        .sort((a, b) => Math.atan2(a.y - cy, a.x - cx) - Math.atan2(b.y - cy, b.x - cx));

    let tlIdx = 0;
    let minSum = Infinity;
    for (let i = 0; i < 4; i++) {
        const sum = clockwise[i].x + clockwise[i].y;
        if (sum < minSum) {
            minSum = sum;
            tlIdx = i;
        }
    }

    return {
        topLeft: clockwise[tlIdx],
        topRight: clockwise[(tlIdx + 1) % 4],
        bottomRight: clockwise[(tlIdx + 2) % 4],
        bottomLeft: clockwise[(tlIdx + 3) % 4],
    };
}

function quadArea(quad: DocumentQuad): number {
    const pts = quadToArray(quad);
    let area2 = 0;
    for (let i = 0; i < 4; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % 4];
        area2 += a.x * b.y - b.x * a.y;
    }

    return Math.abs(area2) * 0.5;
}

function hasReasonableCornerAngles(quad: DocumentQuad): boolean {
    const pts = quadToArray(quad);

    for (let i = 0; i < 4; i++) {
        const prev = pts[(i + 3) % 4];
        const curr = pts[i];
        const next = pts[(i + 1) % 4];

        const v1x = prev.x - curr.x;
        const v1y = prev.y - curr.y;
        const v2x = next.x - curr.x;
        const v2y = next.y - curr.y;
        const len1 = Math.hypot(v1x, v1y);
        const len2 = Math.hypot(v2x, v2y);

        if (len1 < 4 || len2 < 4) {
            return false;
        }

        const cosAngle = Math.max(-1, Math.min(1, (v1x * v2x + v1y * v2y) / (len1 * len2)));
        const angleDeg = (Math.acos(cosAngle) * 180) / Math.PI;

        if (angleDeg < MIN_CORNER_ANGLE_DEG || angleDeg > MAX_CORNER_ANGLE_DEG) {
            return false;
        }
    }

    return true;
}

function isNearlyFullFrame(quad: DocumentQuad, width: number, height: number): boolean {
    const tolX = Math.max(2, width * 0.012);
    const tolY = Math.max(2, height * 0.012);

    return (
        quad.topLeft.x <= tolX
        && quad.topLeft.y <= tolY
        && quad.topRight.x >= width - 1 - tolX
        && quad.topRight.y <= tolY
        && quad.bottomRight.x >= width - 1 - tolX
        && quad.bottomRight.y >= height - 1 - tolY
        && quad.bottomLeft.x <= tolX
        && quad.bottomLeft.y >= height - 1 - tolY
    );
}

function measureQuadEdgeMetrics(ch: WorkingChannels, quad: DocumentQuad): QuadEdgeMetrics {
    const { width, height, lum, cb, cr } = ch;
    const pts = quadToArray(quad);
    const offset = Math.max(2.5, Math.min(4.5, Math.min(width, height) * 0.018));

    let totalDiff = 0;
    let totalSamples = 0;
    let strongSamples = 0;
    let minInsetContrast = Infinity;
    let insetSides = 0;

    for (let i = 0; i < 4; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % 4];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const len = Math.hypot(dx, dy);

        if (len < 6) {
            continue;
        }

        const inX = -dy / len;
        const inY = dx / len;

        let sideDiff = 0;
        let sideSamples = 0;

        for (let k = 1; k <= 9; k++) {
            const t = k / 10;
            const mx = a.x + dx * t;
            const my = a.y + dy * t;
            const outX = mx - inX * offset;
            const outY = my - inY * offset;

            if (outX < 0 || outX > width - 1 || outY < 0 || outY > height - 1) {
                continue;
            }

            const inPx = mx + inX * offset;
            const inPy = my + inY * offset;

            const dLum = sampleFieldBilinear(lum, width, height, inPx, inPy)
                - sampleFieldBilinear(lum, width, height, outX, outY);
            const dCb = (sampleFieldBilinear(cb, width, height, inPx, inPy)
                - sampleFieldBilinear(cb, width, height, outX, outY)) * CHROMA_WEIGHT;
            const dCr = (sampleFieldBilinear(cr, width, height, inPx, inPy)
                - sampleFieldBilinear(cr, width, height, outX, outY)) * CHROMA_WEIGHT;

            const contrast = Math.hypot(dLum, dCb, dCr);
            sideDiff += contrast;
            sideSamples++;
            totalDiff += contrast;
            totalSamples++;

            if (contrast >= 14) {
                strongSamples++;
            }
        }

        if (sideSamples >= 3) {
            insetSides++;
            const sideMean = sideDiff / sideSamples;
            if (sideMean < minInsetContrast) {
                minInsetContrast = sideMean;
            }
        }
    }

    return {
        meanContrast: totalSamples > 0 ? totalDiff / totalSamples : 0,
        minInsetContrast: Number.isFinite(minInsetContrast) ? minInsetContrast : 0,
        supportRatio: totalSamples > 0 ? strongSamples / totalSamples : 0,
        insetSides,
    };
}

function evaluateMaskCandidate(
    rawMask: Uint8Array,
    ch: WorkingChannels,
    grads: SobelGradients,
): CandidateQuad | null {
    const { width, height } = ch;
    const closeRadius = Math.max(1, Math.min(2, Math.round(Math.min(width, height) / 140)));
    const mask = morphCloseMask(rawMask, width, height, closeRadius);

    const component = extractLargestComponentBoundary(mask, width, height);
    if (!component || component.boundary.length < 4) {
        return null;
    }

    const hull = convexHull(component.boundary);
    const rawCorners = reduceHullToQuad(hull);
    if (!rawCorners) {
        return null;
    }

    const lineRefined = refineQuadWithLineFit(rawCorners, component.boundary, width, height);
    const orderedPx = orderCorners(lineRefined);
    if (!isValidConvexQuad(orderedPx)) {
        return null;
    }

    const snappedPx = snapQuadToGradients(orderedPx, ch, grads);
    const orderedMetrics = measureQuadEdgeMetrics(ch, orderedPx);
    const snappedMetrics = measureQuadEdgeMetrics(ch, snappedPx);
    const chosenPx = snappedMetrics.meanContrast >= orderedMetrics.meanContrast * 0.96
        && snappedMetrics.minInsetContrast >= orderedMetrics.minInsetContrast * 0.92
        ? snappedPx
        : orderedPx;
    const metrics = chosenPx === snappedPx ? snappedMetrics : orderedMetrics;

    if (!isValidConvexQuad(chosenPx) || !hasReasonableCornerAngles(chosenPx)) {
        return null;
    }

    if (isNearlyFullFrame(chosenPx, width, height)) {
        return null;
    }

    const area = quadArea(chosenPx);
    const areaRatio = area / (width * height);
    if (areaRatio < MIN_AREA_RATIO || areaRatio > MAX_AREA_RATIO) {
        return null;
    }

    const solidity = component.count / Math.max(1, area);
    if (solidity < 0.72) {
        return null;
    }

    if (metrics.insetSides < 2) {
        return null;
    }

    if (metrics.meanContrast < 18 || metrics.minInsetContrast < 11 || metrics.supportRatio < 0.45) {
        return null;
    }

    const sideCompleteness = 0.75 + 0.25 * (metrics.insetSides / 4);
    const solidityFactor = clamp01((solidity - 0.65) / 0.30);
    const score =
        metrics.meanContrast
        * (0.55 + 0.45 * metrics.supportRatio)
        * (0.65 + 0.35 * clamp01(metrics.minInsetContrast / 40))
        * sideCompleteness
        * (0.75 + 0.25 * solidityFactor);

    return {
        quad: chosenPx,
        score,
        metrics,
    };
}

export function detectDocumentQuad(imageData: ImageDataLike): QuadDetectionResult {
    const { width: srcW, height: srcH, data } = imageData;

    if (srcW < 16 || srcH < 16 || data.length < srcW * srcH * 4) {
        return fallbackResult();
    }

    const downsampled = downsampleChannels(imageData);
    const { width, height } = downsampled;
    const smoothCh: WorkingChannels = {
        width,
        height,
        lum: gaussianBlur3x3(gaussianBlur3x3(downsampled.lum, width, height), width, height),
        cb: gaussianBlur3x3(downsampled.cb, width, height),
        cr: gaussianBlur3x3(downsampled.cr, width, height),
    };
    const grads = computeSobelGradients(smoothCh);

    const candidateMasks: Uint8Array[] = [];

    // Strategy 1: Classic bidirectional Otsu luminance segmentation
    const { threshold, contrastDelta } = computeOtsuThreshold(smoothCh.lum);
    if (contrastDelta >= MIN_CONTRAST_DELTA) {
        const otsuMask = buildOtsuForegroundMask(smoothCh.lum, width, height, threshold);
        if (otsuMask) {
            candidateMasks.push(otsuMask);
        }
    }

    // Strategy 2: Color-distance (YCbCr) border-vs-paper segmentation (light wood / colored desks & shadows)
    const colorMask = buildColorDistanceMask(smoothCh);
    if (colorMask) {
        candidateMasks.push(colorMask);
    }

    // Strategy 3: Illumination-plane compensated segmentation (heavy directional phone shadows)
    const shadowMask = buildShadowCompensatedMask(smoothCh);
    if (shadowMask) {
        candidateMasks.push(shadowMask);
    }

    let bestCandidate: CandidateQuad | null = null;
    for (const mask of candidateMasks) {
        const candidate = evaluateMaskCandidate(mask, smoothCh, grads);
        if (candidate && (!bestCandidate || candidate.score > bestCandidate.score)) {
            bestCandidate = candidate;
        }
    }

    if (!bestCandidate) {
        return fallbackResult();
    }

    const normX = width - 1;
    const normY = height - 1;
    const chosenPx = bestCandidate.quad;
    const normalizedQuad: DocumentQuad = {
        topLeft: { x: clamp01(chosenPx.topLeft.x / normX), y: clamp01(chosenPx.topLeft.y / normY) },
        topRight: { x: clamp01(chosenPx.topRight.x / normX), y: clamp01(chosenPx.topRight.y / normY) },
        bottomRight: { x: clamp01(chosenPx.bottomRight.x / normX), y: clamp01(chosenPx.bottomRight.y / normY) },
        bottomLeft: { x: clamp01(chosenPx.bottomLeft.x / normX), y: clamp01(chosenPx.bottomLeft.y / normY) },
    };

    if (!isValidConvexQuad(normalizedQuad)) {
        return fallbackResult();
    }

    const confidence = clamp01(
        ((bestCandidate.metrics.meanContrast - 16) / 75) * 0.65
        + bestCandidate.metrics.supportRatio * 0.35,
    );

    return {
        quad: normalizedQuad,
        detected: true,
        confidence,
    };
}
