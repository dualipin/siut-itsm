export function clamp01(value: number): number {
    if (value <= 0) {
        return 0;
    }
    if (value >= 1) {
        return 1;
    }

    return value;
}

export function clampByte(value: number): number {
    if (value <= 0) {
        return 0;
    }
    if (value >= 255) {
        return 255;
    }

    return Math.round(value);
}
