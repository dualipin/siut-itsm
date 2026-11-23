import { clamp01 } from './image-utils.ts';
import { quadToArray, type DocumentQuad, type Point2D } from './quad-detector.ts';

export interface StageRect {
    left: number;
    top: number;
    width: number;
    height: number;
}

function toPercentCoord(unitValue: number): number {
    return Math.round(clamp01(unitValue) * 10000) / 100;
}

export function normalizePointerQuadCorner(
    clientX: number,
    clientY: number,
    rect: StageRect | null | undefined,
): Point2D | null {
    if (
        !rect
        || !Number.isFinite(clientX)
        || !Number.isFinite(clientY)
        || !Number.isFinite(rect.left)
        || !Number.isFinite(rect.top)
        || !Number.isFinite(rect.width)
        || !Number.isFinite(rect.height)
        || rect.width <= 0
        || rect.height <= 0
    ) {
        return null;
    }

    return {
        x: clamp01((clientX - rect.left) / rect.width),
        y: clamp01((clientY - rect.top) / rect.height),
    };
}

export function formatQuadPolygonPoints(quad: DocumentQuad | null | undefined): string {
    if (!quad) {
        return '';
    }

    return quadToArray(quad)
        .map((pt) => `${toPercentCoord(pt.x)},${toPercentCoord(pt.y)}`)
        .join(' ');
}

export function formatQuadMaskPath(quad: DocumentQuad | null | undefined): string {
    if (!quad) {
        return '';
    }

    const segments = quadToArray(quad)
        .map((pt) => `${toPercentCoord(pt.x)},${toPercentCoord(pt.y)}`)
        .join(' L');

    return `M0,0 H100 V100 H0 Z M${segments} Z`;
}
