export const LETTER_WIDTH_PT = 612;

export interface PdfJpegPage {
    jpegBytes: Uint8Array;
    width: number;
    height: number;
}

const ASCII_ENCODER = new TextEncoder();
const PDF_HEADER = Uint8Array.from([
    0x25, 0x50, 0x44, 0x46, 0x2d, 0x31, 0x2e, 0x34, 0x0a, // %PDF-1.4\n
    0x25, 0xe2, 0xe3, 0xcf, 0xd3, 0x0a, // %\xE2\xE3\xCF\xD3\n
]);

function formatPdfNumber(value: number): string {
    if (Number.isInteger(value)) {
        return String(value);
    }

    return value.toFixed(2).replace(/\.?0+$/, '');
}

function detectJpegColorSpace(jpegBytes: Uint8Array): '/DeviceGray' | '/DeviceRGB' {
    let i = 2;

    while (i + 9 < jpegBytes.length) {
        if (jpegBytes[i] !== 0xff) {
            i++;
            continue;
        }

        const marker = jpegBytes[i + 1];

        if (marker === 0xc0 || marker === 0xc1 || marker === 0xc2) {
            const components = jpegBytes[i + 9];

            return components === 1 ? '/DeviceGray' : '/DeviceRGB';
        }

        if (marker === 0xd8 || marker === 0xd9 || marker === 0x00 || (marker >= 0xd0 && marker <= 0xd7)) {
            i += 2;
            continue;
        }

        if (i + 3 >= jpegBytes.length) {
            break;
        }

        const segmentLength = (jpegBytes[i + 2] << 8) | jpegBytes[i + 3];

        if (segmentLength < 2) {
            break;
        }

        i += 2 + segmentLength;
    }

    return '/DeviceRGB';
}

export function computeLetterPageDimensions(widthPx: number, heightPx: number): { widthPt: number; heightPt: number } {
    if (!Number.isFinite(widthPx) || !Number.isFinite(heightPx) || widthPx <= 0 || heightPx <= 0) {
        throw new Error('Las dimensiones de cada página deben ser números positivos.');
    }

    const heightPt = (heightPx / widthPx) * LETTER_WIDTH_PT;

    return {
        widthPt: LETTER_WIDTH_PT,
        heightPt: Math.round(heightPt * 100) / 100,
    };
}

export function estimatePdfByteLength(pages: ReadonlyArray<Pick<PdfJpegPage, 'jpegBytes'>>): number {
    if (pages.length === 0) {
        return 0;
    }

    const imageBytes = pages.reduce((sum, page) => sum + page.jpegBytes.byteLength, 0);

    // ~420 bytes per page objects + ~240 bytes header/catalog/pages/xref/trailer
    return imageBytes + pages.length * 420 + 240;
}

/**
 * Assemble a standard PDF 1.4 document embedding JPEG pages directly via /DCTDecode.
 */
export function buildPdfFromJpegPages(pages: ReadonlyArray<PdfJpegPage>): Uint8Array {
    if (pages.length === 0) {
        throw new Error('Se requiere al menos una página para generar el PDF.');
    }

    const chunks: Uint8Array[] = [];
    let byteLength = 0;

    const pushBytes = (bytes: Uint8Array): void => {
        chunks.push(bytes);
        byteLength += bytes.byteLength;
    };

    const pushAscii = (text: string): void => {
        pushBytes(ASCII_ENCODER.encode(text));
    };

    pushBytes(PDF_HEADER);

    const totalObjects = 2 + pages.length * 3;
    const offsets = new Array<number>(totalObjects + 1).fill(0);
    const kidsRefs: string[] = [];

    for (let i = 0; i < pages.length; i++) {
        kidsRefs.push(`${3 + i * 3} 0 R`);
    }

    offsets[1] = byteLength;
    pushAscii('1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n');

    offsets[2] = byteLength;
    pushAscii(`2 0 obj\n<< /Type /Pages /Kids [${kidsRefs.join(' ')}] /Count ${pages.length} >>\nendobj\n`);

    for (let i = 0; i < pages.length; i++) {
        const page = pages[i];

        if (!(page.jpegBytes instanceof Uint8Array) || page.jpegBytes.byteLength === 0) {
            throw new Error(`La página ${i + 1} tiene una imagen JPEG vacía.`);
        }

        const { widthPt, heightPt } = computeLetterPageDimensions(page.width, page.height);
        const wStr = formatPdfNumber(widthPt);
        const hStr = formatPdfNumber(heightPt);
        const colorSpace = detectJpegColorSpace(page.jpegBytes);

        const pageObjId = 3 + i * 3;
        const contentObjId = 4 + i * 3;
        const imageObjId = 5 + i * 3;

        offsets[pageObjId] = byteLength;
        pushAscii(
            `${pageObjId} 0 obj\n`
            + `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${wStr} ${hStr}] `
            + `/Resources << /XObject << /Im1 ${imageObjId} 0 R >> /ProcSet [/PDF /ImageB /ImageC] >> `
            + `/Contents ${contentObjId} 0 R >>\nendobj\n`,
        );

        const contentStream = `q\n${wStr} 0 0 ${hStr} 0 0 cm\n/Im1 Do\nQ\n`;
        offsets[contentObjId] = byteLength;
        pushAscii(
            `${contentObjId} 0 obj\n<< /Length ${contentStream.length} >>\nstream\n${contentStream}endstream\nendobj\n`,
        );

        offsets[imageObjId] = byteLength;
        pushAscii(
            `${imageObjId} 0 obj\n`
            + `<< /Type /XObject /Subtype /Image /Width ${Math.round(page.width)} /Height ${Math.round(page.height)} `
            + `/ColorSpace ${colorSpace} /BitsPerComponent 8 /Filter /DCTDecode /Length ${page.jpegBytes.byteLength} >>\n`
            + 'stream\n',
        );
        pushBytes(page.jpegBytes);
        pushAscii('\nendstream\nendobj\n');
    }

    const xrefOffset = byteLength;
    pushAscii(`xref\n0 ${totalObjects + 1}\n`);
    pushAscii('0000000000 65535 f \n');

    for (let objId = 1; objId <= totalObjects; objId++) {
        const paddedOffset = String(offsets[objId]).padStart(10, '0');
        pushAscii(`${paddedOffset} 00000 n \n`);
    }

    pushAscii(`trailer\n<< /Size ${totalObjects + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`);

    const output = new Uint8Array(byteLength);
    let cursor = 0;

    for (const chunk of chunks) {
        output.set(chunk, cursor);
        cursor += chunk.byteLength;
    }

    return output;
}
