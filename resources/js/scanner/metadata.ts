export const MAX_PDF_BYTES = 15 * 1024 * 1024;

export interface SuggestedFilenameOptions {
    referenceNumber?: string | null;
    name?: string | null;
    now?: Date;
}

export interface PdfByteSizeValidation {
    valid: boolean;
    exceedsLimit: boolean;
    byteLength: number;
    maxBytes: number;
    remainingBytes: number;
    formattedSize: string;
    formattedMax: string;
}

function sanitizeFilenameBase(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-zA-Z0-9._-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^[-_.]+|[-_.]+$/g, '')
        .slice(0, 120);
}

function formatTimestampSuffix(now: Date): string {
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');

    return `escaneo-${year}${month}${day}-${hours}${minutes}`;
}

export function buildSuggestedPdfFilename(options: SuggestedFilenameOptions = {}): string {
    const candidates = [options.referenceNumber ?? '', options.name ?? ''];

    for (const candidate of candidates) {
        const sanitized = sanitizeFilenameBase(candidate.trim());
        if (sanitized !== '' && /[a-zA-Z0-9]/.test(sanitized)) {
            return `${sanitized}.pdf`;
        }
    }

    return `${formatTimestampSuffix(options.now ?? new Date())}.pdf`;
}

export function ensurePdfFilename(rawFilename: string, fallbackOptions: SuggestedFilenameOptions = {}): string {
    const withoutExtension = rawFilename.trim().replace(/\.pdf$/i, '');
    const sanitized = sanitizeFilenameBase(withoutExtension);

    if (sanitized !== '' && /[a-zA-Z0-9]/.test(sanitized)) {
        return `${sanitized}.pdf`;
    }

    return buildSuggestedPdfFilename(fallbackOptions);
}

export function formatByteSize(byteLength: number): string {
    const bytes = Math.max(0, byteLength);

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function validatePdfByteSize(byteLength: number, maxBytes: number = MAX_PDF_BYTES): PdfByteSizeValidation {
    const safeBytes = Number.isFinite(byteLength) ? Math.max(0, Math.trunc(byteLength)) : 0;
    const exceedsLimit = safeBytes > maxBytes;
    const valid = safeBytes > 0 && !exceedsLimit;

    return {
        valid,
        exceedsLimit,
        byteLength: safeBytes,
        maxBytes,
        remainingBytes: maxBytes - safeBytes,
        formattedSize: formatByteSize(safeBytes),
        formattedMax: formatByteSize(maxBytes),
    };
}
