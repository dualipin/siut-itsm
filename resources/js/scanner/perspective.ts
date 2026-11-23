import {
    isValidConvexQuad,
    type DocumentQuad,
    type ImageDataLike,
    type Point2D,
} from './quad-detector.ts';

export const DEFAULT_MAX_LONG_SIDE = 1600;

export type HomographyMatrix = [
    number, number, number,
    number, number, number,
    number, number, number,
];

export interface WarpOptions {
    maxLongSide?: number;
}

export function computeWarpedDimensions(
    quad: DocumentQuad,
    srcWidth: number,
    srcHeight: number,
    maxLongSide: number = DEFAULT_MAX_LONG_SIDE,
): { width: number; height: number } {
    if (srcWidth <= 0 || srcHeight <= 0) {
        throw new Error('Las dimensiones de la imagen origen deben ser positivas.');
    }

    const tl = { x: quad.topLeft.x * srcWidth, y: quad.topLeft.y * srcHeight };
    const tr = { x: quad.topRight.x * srcWidth, y: quad.topRight.y * srcHeight };
    const br = { x: quad.bottomRight.x * srcWidth, y: quad.bottomRight.y * srcHeight };
    const bl = { x: quad.bottomLeft.x * srcWidth, y: quad.bottomLeft.y * srcHeight };

    const topWidth = Math.hypot(tr.x - tl.x, tr.y - tl.y);
    const bottomWidth = Math.hypot(br.x - bl.x, br.y - bl.y);
    const leftHeight = Math.hypot(bl.x - tl.x, bl.y - tl.y);
    const rightHeight = Math.hypot(br.x - tr.x, br.y - tr.y);

    const naturalWidth = Math.max(1, Math.max(topWidth, bottomWidth));
    const naturalHeight = Math.max(1, Math.max(leftHeight, rightHeight));

    const longSide = Math.max(naturalWidth, naturalHeight);
    const scale = longSide > maxLongSide ? maxLongSide / longSide : 1;

    return {
        width: Math.max(1, Math.round(naturalWidth * scale)),
        height: Math.max(1, Math.round(naturalHeight * scale)),
    };
}

function solveLinearSystem8(matrix: number[][], rhs: number[]): number[] {
    const n = 8;
    const a = matrix.map((row, r) => [...row, rhs[r]]);

    for (let col = 0; col < n; col++) {
        let pivotRow = col;
        let maxAbs = Math.abs(a[col][col]);

        for (let row = col + 1; row < n; row++) {
            const val = Math.abs(a[row][col]);
            if (val > maxAbs) {
                maxAbs = val;
                pivotRow = row;
            }
        }

        if (maxAbs < 1e-12) {
            throw new Error('No se pudo resolver la transformación proyectiva para las esquinas indicadas.');
        }

        if (pivotRow !== col) {
            const tmp = a[col];
            a[col] = a[pivotRow];
            a[pivotRow] = tmp;
        }

        const pivot = a[col][col];
        for (let j = col; j <= n; j++) {
            a[col][j] /= pivot;
        }

        for (let row = 0; row < n; row++) {
            if (row === col) {
                continue;
            }
            const factor = a[row][col];
            if (factor === 0) {
                continue;
            }
            for (let j = col; j <= n; j++) {
                a[row][j] -= factor * a[col][j];
            }
        }
    }

    return a.map((row) => row[n]);
}

export function solveHomography(
    fromPoints: readonly [Point2D, Point2D, Point2D, Point2D],
    toPoints: readonly [Point2D, Point2D, Point2D, Point2D],
): HomographyMatrix {
    const matrix: number[][] = [];
    const rhs: number[] = [];

    for (let i = 0; i < 4; i++) {
        const u = fromPoints[i].x;
        const v = fromPoints[i].y;
        const x = toPoints[i].x;
        const y = toPoints[i].y;

        matrix.push([u, v, 1, 0, 0, 0, -u * x, -v * x]);
        rhs.push(x);

        matrix.push([0, 0, 0, u, v, 1, -u * y, -v * y]);
        rhs.push(y);
    }

    const h = solveLinearSystem8(matrix, rhs);

    return [h[0], h[1], h[2], h[3], h[4], h[5], h[6], h[7], 1];
}

export function projectPoint(h: HomographyMatrix, u: number, v: number): Point2D {
    const w = h[6] * u + h[7] * v + h[8];

    return {
        x: (h[0] * u + h[1] * v + h[2]) / w,
        y: (h[3] * u + h[4] * v + h[5]) / w,
    };
}

export function warpPerspective(
    source: ImageDataLike,
    quad: DocumentQuad,
    options: WarpOptions = {},
): ImageDataLike {
    const { width: srcW, height: srcH, data: src } = source;
    if (srcW < 2 || srcH < 2 || src.length < srcW * srcH * 4) {
        throw new Error('La imagen de origen es demasiado pequeña para aplicar perspectiva.');
    }

    if (!isValidConvexQuad(quad)) {
        throw new Error('Las 4 esquinas deben formar un cuadrilátero convexo válido.');
    }

    const maxLongSide = options.maxLongSide ?? DEFAULT_MAX_LONG_SIDE;
    const { width: outW, height: outH } = computeWarpedDimensions(quad, srcW, srcH, maxLongSide);

    const maxSrcX = srcW - 1;
    const maxSrcY = srcH - 1;
    const dstCorners: [Point2D, Point2D, Point2D, Point2D] = [
        { x: 0, y: 0 },
        { x: Math.max(1, outW - 1), y: 0 },
        { x: Math.max(1, outW - 1), y: Math.max(1, outH - 1) },
        { x: 0, y: Math.max(1, outH - 1) },
    ];
    const srcCorners: [Point2D, Point2D, Point2D, Point2D] = [
        { x: quad.topLeft.x * maxSrcX, y: quad.topLeft.y * maxSrcY },
        { x: quad.topRight.x * maxSrcX, y: quad.topRight.y * maxSrcY },
        { x: quad.bottomRight.x * maxSrcX, y: quad.bottomRight.y * maxSrcY },
        { x: quad.bottomLeft.x * maxSrcX, y: quad.bottomLeft.y * maxSrcY },
    ];

    const h = solveHomography(dstCorners, srcCorners);
    const outData = new Uint8ClampedArray(outW * outH * 4);

    const h0 = h[0];
    const h1 = h[1];
    const h2 = h[2];
    const h3 = h[3];
    const h4 = h[4];
    const h5 = h[5];
    const h6 = h[6];
    const h7 = h[7];

    for (let dy = 0; dy < outH; dy++) {
        let numX = h1 * dy + h2;
        let numY = h4 * dy + h5;
        let denom = h7 * dy + 1;
        let outIdx = dy * outW * 4;

        for (let dx = 0; dx < outW; dx++) {
            let sx = numX / denom;
            let sy = numY / denom;

            if (sx < 0) {
                sx = 0;
            } else if (sx > maxSrcX) {
                sx = maxSrcX;
            }

            if (sy < 0) {
                sy = 0;
            } else if (sy > maxSrcY) {
                sy = maxSrcY;
            }

            const x0 = sx | 0;
            const y0 = sy | 0;
            const x1 = x0 < maxSrcX ? x0 + 1 : maxSrcX;
            const y1 = y0 < maxSrcY ? y0 + 1 : maxSrcY;
            const fx = sx - x0;
            const fy = sy - y0;
            const invFx = 1 - fx;
            const invFy = 1 - fy;

            const w00 = invFx * invFy;
            const w10 = fx * invFy;
            const w01 = invFx * fy;
            const w11 = fx * fy;

            const i00 = (y0 * srcW + x0) * 4;
            const i10 = (y0 * srcW + x1) * 4;
            const i01 = (y1 * srcW + x0) * 4;
            const i11 = (y1 * srcW + x1) * 4;

            outData[outIdx] = src[i00] * w00 + src[i10] * w10 + src[i01] * w01 + src[i11] * w11;
            outData[outIdx + 1] = src[i00 + 1] * w00 + src[i10 + 1] * w10 + src[i01 + 1] * w01 + src[i11 + 1] * w11;
            outData[outIdx + 2] = src[i00 + 2] * w00 + src[i10 + 2] * w10 + src[i01 + 2] * w01 + src[i11 + 2] * w11;
            outData[outIdx + 3] = src[i00 + 3] * w00 + src[i10 + 3] * w10 + src[i01 + 3] * w01 + src[i11 + 3] * w11;

            numX += h0;
            numY += h3;
            denom += h6;
            outIdx += 4;
        }
    }

    return {
        width: outW,
        height: outH,
        data: outData,
    };
}
