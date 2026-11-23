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

const MAX_WORKING_SIDE = 160;
const MIN_CONTRAST_DELTA = 28;
const MIN_AREA_RATIO = 0.10;
const MAX_AREA_RATIO = 0.95;

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

        const cross = (b.x - a.x) * (c.y - b.y) - (b.y - a.y) * (c.x - b.x);
        if (Math.abs(cross) < 1e-6) {
            return false;
        }

        const currentSign = cross > 0 ? 1 : -1;
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

function downsampleLuminance(imageData: ImageDataLike): { width: number; height: number; lum: Float32Array } {
    const { width: srcW, height: srcH, data } = imageData;
    const longSide = Math.max(srcW, srcH);

    if (longSide <= MAX_WORKING_SIDE) {
        const lum = new Float32Array(srcW * srcH);
        for (let i = 0; i < srcW * srcH; i++) {
            const o = i * 4;
            lum[i] = 0.299 * data[o] + 0.587 * data[o + 1] + 0.114 * data[o + 2];
        }

        return { width: srcW, height: srcH, lum };
    }

    const scale = MAX_WORKING_SIDE / longSide;
    const dstW = Math.max(16, Math.round(srcW * scale));
    const dstH = Math.max(16, Math.round(srcH * scale));
    const lum = new Float32Array(dstW * dstH);

    for (let y = 0; y < dstH; y++) {
        const sy = Math.min(srcH - 1, Math.floor(((y + 0.5) * srcH) / dstH));
        for (let x = 0; x < dstW; x++) {
            const sx = Math.min(srcW - 1, Math.floor(((x + 0.5) * srcW) / dstW));
            const o = (sy * srcW + sx) * 4;
            lum[y * dstW + x] = 0.299 * data[o] + 0.587 * data[o + 1] + 0.114 * data[o + 2];
        }
    }

    return { width: dstW, height: dstH, lum };
}

function boxBlur3x3(src: Float32Array, width: number, height: number): Float32Array {
    const dst = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const y0 = Math.max(0, y - 1);
        const y1 = Math.min(height - 1, y + 1);

        for (let x = 0; x < width; x++) {
            const x0 = Math.max(0, x - 1);
            const x1 = Math.min(width - 1, x + 1);

            let sum = 0;
            let count = 0;
            for (let ky = y0; ky <= y1; ky++) {
                const row = ky * width;
                for (let kx = x0; kx <= x1; kx++) {
                    sum += src[row + kx];
                    count++;
                }
            }
            dst[y * width + x] = sum / count;
        }
    }

    return dst;
}

function computeOtsuThreshold(lum: Float32Array): { threshold: number; contrastDelta: number } {
    const hist = new Int32Array(256);
    const total = lum.length;
    let totalSum = 0;

    for (let i = 0; i < total; i++) {
        const bin = Math.max(0, Math.min(255, Math.round(lum[i])));
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

function buildForegroundMask(
    lum: Float32Array,
    width: number,
    height: number,
    threshold: number,
): Uint8Array | null {
    let borderHigh = 0;
    let borderTotal = 0;
    const borderThickness = Math.max(2, Math.floor(Math.min(width, height) * 0.03));

    for (let y = 0; y < height; y++) {
        const isBorderRow = y < borderThickness || y >= height - borderThickness;
        for (let x = 0; x < width; x++) {
            const isBorderCol = x < borderThickness || x >= width - borderThickness;
            if (isBorderRow || isBorderCol) {
                borderTotal++;
                if (lum[y * width + x] >= threshold) {
                    borderHigh++;
                }
            }
        }
    }

    let centerHigh = 0;
    let centerTotal = 0;
    const cx0 = Math.floor(width * 0.25);
    const cx1 = Math.ceil(width * 0.75);
    const cy0 = Math.floor(height * 0.25);
    const cy1 = Math.ceil(height * 0.75);

    for (let y = cy0; y < cy1; y++) {
        for (let x = cx0; x < cx1; x++) {
            centerTotal++;
            if (lum[y * width + x] >= threshold) {
                centerHigh++;
            }
        }
    }

    const pBorderHigh = borderHigh / Math.max(1, borderTotal);
    const pCenterHigh = centerHigh / Math.max(1, centerTotal);

    let brightForeground: boolean;
    if (pCenterHigh - pBorderHigh >= 0.15) {
        brightForeground = true;
    } else if ((1 - pCenterHigh) - (1 - pBorderHigh) >= 0.15) {
        brightForeground = false;
    } else {
        return null;
    }

    const borderFgRatio = brightForeground ? pBorderHigh : 1 - pBorderHigh;
    if (borderFgRatio > 0.75) {
        return null;
    }

    const mask = new Uint8Array(width * height);
    for (let i = 0; i < lum.length; i++) {
        const isHigh = lum[i] >= threshold;
        mask[i] = isHigh === brightForeground ? 1 : 0;
    }

    return mask;
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

interface Line2D {
    nx: number;
    ny: number;
    c: number;
}

function fitEdgeLine(points: Point2D[], a: Point2D, b: Point2D): Line2D | null {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const len = Math.hypot(dx, dy);
    if (len < 6) {
        return null;
    }

    const ux = dx / len;
    const uy = dy / len;
    const support: Point2D[] = [];

    for (const p of points) {
        const vx = p.x - a.x;
        const vy = p.y - a.y;
        const proj = vx * ux + vy * uy;

        if (proj < len * 0.08 || proj > len * 0.92) {
            continue;
        }

        const perp = Math.abs(vx * -uy + vy * ux);
        if (perp <= 2.5) {
            support.push(p);
        }
    }

    if (support.length < 4) {
        return null;
    }

    let meanX = 0;
    let meanY = 0;
    for (const p of support) {
        meanX += p.x;
        meanY += p.y;
    }
    meanX /= support.length;
    meanY /= support.length;

    let sxx = 0;
    let syy = 0;
    let sxy = 0;
    for (const p of support) {
        const px = p.x - meanX;
        const py = p.y - meanY;
        sxx += px * px;
        syy += py * py;
        sxy += px * py;
    }

    const theta = 0.5 * Math.atan2(2 * sxy, sxx - syy);
    const nx = -Math.sin(theta);
    const ny = Math.cos(theta);
    const c = nx * meanX + ny * meanY;

    return { nx, ny, c };
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
            || Math.hypot(hit.x - fallback.x, hit.y - fallback.y) > Math.max(width, height) * 0.08
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

function sampleLumBilinear(lum: Float32Array, width: number, height: number, x: number, y: number): number {
    const cx = Math.max(0, Math.min(width - 1, x));
    const cy = Math.max(0, Math.min(height - 1, y));
    const x0 = Math.floor(cx);
    const y0 = Math.floor(cy);
    const x1 = Math.min(width - 1, x0 + 1);
    const y1 = Math.min(height - 1, y0 + 1);
    const fx = cx - x0;
    const fy = cy - y0;

    return (
        lum[y0 * width + x0] * (1 - fx) * (1 - fy)
        + lum[y0 * width + x1] * fx * (1 - fy)
        + lum[y1 * width + x0] * (1 - fx) * fy
        + lum[y1 * width + x1] * fx * fy
    );
}

function measureEdgeContrast(lum: Float32Array, width: number, height: number, quad: DocumentQuad): number {
    const pts = quadToArray(quad);
    let totalDiff = 0;
    let samples = 0;

    for (let i = 0; i < 4; i++) {
        const a = pts[i];
        const b = pts[(i + 1) % 4];
        const dx = b.x - a.x;
        const dy = b.y - a.y;
        const len = Math.hypot(dx, dy);
        if (len < 4) {
            continue;
        }

        // Inward normal for clockwise quad in screen coordinates (+y down)
        const inX = -dy / len;
        const inY = dx / len;

        for (let k = 1; k <= 5; k++) {
            const t = k / 6;
            const mx = a.x + dx * t;
            const my = a.y + dy * t;
            const insideLum = sampleLumBilinear(lum, width, height, mx + inX * 3, my + inY * 3);
            const outsideLum = sampleLumBilinear(lum, width, height, mx - inX * 3, my - inY * 3);
            totalDiff += Math.abs(insideLum - outsideLum);
            samples++;
        }
    }

    return samples > 0 ? totalDiff / samples : 0;
}

export function detectDocumentQuad(imageData: ImageDataLike): QuadDetectionResult {
    const { width: srcW, height: srcH, data } = imageData;

    if (srcW < 16 || srcH < 16 || data.length < srcW * srcH * 4) {
        return fallbackResult();
    }

    const { width, height, lum } = downsampleLuminance(imageData);
    const blurred = boxBlur3x3(lum, width, height);
    const { threshold, contrastDelta } = computeOtsuThreshold(blurred);

    if (contrastDelta < MIN_CONTRAST_DELTA) {
        return fallbackResult();
    }

    const mask = buildForegroundMask(blurred, width, height, threshold);
    if (!mask) {
        return fallbackResult();
    }

    const component = extractLargestComponentBoundary(mask, width, height);
    if (!component || component.boundary.length < 4) {
        return fallbackResult();
    }

    const hull = convexHull(component.boundary);
    const rawCorners = reduceHullToQuad(hull);
    if (!rawCorners) {
        return fallbackResult();
    }

    const refined = refineQuadWithLineFit(rawCorners, component.boundary, width, height);
    const orderedPx = orderCorners(refined);

    if (!isValidConvexQuad(orderedPx)) {
        return fallbackResult();
    }

    const edgeContrast = measureEdgeContrast(blurred, width, height, orderedPx);
    if (edgeContrast < 18) {
        return fallbackResult();
    }

    const normX = width - 1;
    const normY = height - 1;
    const normalizedQuad: DocumentQuad = {
        topLeft: { x: clamp01(orderedPx.topLeft.x / normX), y: clamp01(orderedPx.topLeft.y / normY) },
        topRight: { x: clamp01(orderedPx.topRight.x / normX), y: clamp01(orderedPx.topRight.y / normY) },
        bottomRight: { x: clamp01(orderedPx.bottomRight.x / normX), y: clamp01(orderedPx.bottomRight.y / normY) },
        bottomLeft: { x: clamp01(orderedPx.bottomLeft.x / normX), y: clamp01(orderedPx.bottomLeft.y / normY) },
    };

    if (!isValidConvexQuad(normalizedQuad)) {
        return fallbackResult();
    }

    const confidence = clamp01((edgeContrast - 18) / 80 + 0.3);

    return {
        quad: normalizedQuad,
        detected: true,
        confidence,
    };
}
