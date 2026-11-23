import {
    buildPdfFromJpegPages,
    estimatePdfByteLength,
    type PdfJpegPage,
} from './scanner/pdf.ts';
import {
    MAX_PDF_BYTES,
    buildSuggestedPdfFilename,
    ensurePdfFilename,
    formatByteSize,
    validatePdfByteSize,
    type SuggestedFilenameOptions,
} from './scanner/metadata.ts';
import {
    FULL_DOCUMENT_QUAD,
    cloneQuad,
    detectDocumentQuad,
    isValidConvexQuad,
    type DocumentQuad,
    type ImageDataLike,
} from './scanner/quad-detector.ts';
import {
    DEFAULT_MAX_LONG_SIDE,
    warpPerspective,
} from './scanner/perspective.ts';
import {
    assessCameraEnvironment,
    buildRearCameraConstraints,
    buildTorchConstraints,
    mapCameraErrorToSpanish,
    supportsTrackTorch,
} from './scanner/camera.ts';
import {
    DEFAULT_SCAN_FILTER,
    applyScanFilter,
    movePage,
    normalizeRotation,
    rotateImageData,
    type QuarterTurnRotation,
    type ScanFilter,
} from './scanner/filters.ts';

export type { QuarterTurnRotation, ScanFilter };
export { DEFAULT_SCAN_FILTER, applyScanFilter, movePage, normalizeRotation, rotateImageData };

export interface ScannedPageItem extends PdfJpegPage {
    id: string;
    previewUrl: string;
    sourceName: string;
    originalSource: Blob;
    originalPreviewUrl: string;
    originalWidth: number;
    originalHeight: number;
    quad: DocumentQuad;
    autoDetected: boolean;
    filter: ScanFilter;
    rotation: QuarterTurnRotation;
}

export interface EncodeImageOptions {
    maxLongSide?: number;
    quality?: number;
    filter?: ScanFilter;
    rotation?: QuarterTurnRotation | number;
}

export interface AddPageFromSourceOptions extends EncodeImageOptions {
    sourceName?: string;
}

export type StartRearCameraResult =
    | {
          ok: true;
          stream: MediaStream;
          track: MediaStreamTrack;
          torchSupported: boolean;
      }
    | {
          ok: false;
          message: string;
      };

const DEFAULT_JPEG_QUALITY = 0.78;
const CAPTURE_FRAME_JPEG_QUALITY = 0.92;
const MAX_WORKING_LONG_SIDE = 2400;
const DETECTION_LONG_SIDE = 160;

interface DecodedImageSource {
    width: number;
    height: number;
    draw: (ctx: CanvasRenderingContext2D, targetWidth: number, targetHeight: number) => void;
    cleanup: () => void;
}

async function loadImageSource(source: Blob, sourceName: string): Promise<DecodedImageSource> {
    if (typeof createImageBitmap === 'function') {
        const bitmap = await createImageBitmap(source);

        return {
            width: bitmap.width,
            height: bitmap.height,
            draw: (ctx, targetWidth, targetHeight) => ctx.drawImage(bitmap, 0, 0, targetWidth, targetHeight),
            cleanup: () => bitmap.close(),
        };
    }

    const objectUrl = URL.createObjectURL(source);

    try {
        const image = await new Promise<HTMLImageElement>((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error(`No se pudo cargar la imagen "${sourceName}".`));
            img.src = objectUrl;
        });

        return {
            width: image.naturalWidth || image.width,
            height: image.naturalHeight || image.height,
            draw: (ctx, targetWidth, targetHeight) => ctx.drawImage(image, 0, 0, targetWidth, targetHeight),
            cleanup: () => URL.revokeObjectURL(objectUrl),
        };
    } catch (error) {
        URL.revokeObjectURL(objectUrl);
        throw error;
    }
}

function canvasToJpegBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(new Error('No se pudo codificar la página a formato JPEG.'));

                    return;
                }

                resolve(blob);
            },
            'image/jpeg',
            quality,
        );
    });
}

function isFullQuad(quad: DocumentQuad): boolean {
    const eps = 1e-3;

    return (
        Math.abs(quad.topLeft.x) < eps
        && Math.abs(quad.topLeft.y) < eps
        && Math.abs(quad.topRight.x - 1) < eps
        && Math.abs(quad.topRight.y) < eps
        && Math.abs(quad.bottomRight.x - 1) < eps
        && Math.abs(quad.bottomRight.y - 1) < eps
        && Math.abs(quad.bottomLeft.x) < eps
        && Math.abs(quad.bottomLeft.y - 1) < eps
    );
}

function drawScaledToCanvas(
    source: DecodedImageSource,
    maxLongSide: number,
): { canvas: HTMLCanvasElement; width: number; height: number } {
    const longSide = Math.max(source.width, source.height);
    const scale = longSide > maxLongSide ? maxLongSide / longSide : 1;
    const width = Math.max(1, Math.round(source.width * scale));
    const height = Math.max(1, Math.round(source.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    if (!ctx) {
        canvas.width = 0;
        canvas.height = 0;
        throw new Error('No fue posible inicializar el lienzo de procesamiento de imagen.');
    }

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    source.draw(ctx, width, height);

    return { canvas, width, height };
}

function detectQuadFromSource(source: DecodedImageSource): { quad: DocumentQuad; detected: boolean } {
    const { canvas, width, height } = drawScaledToCanvas(source, DETECTION_LONG_SIDE);

    try {
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return { quad: cloneQuad(FULL_DOCUMENT_QUAD), detected: false };
        }

        const imageData = ctx.getImageData(0, 0, width, height);
        const result = detectDocumentQuad(imageData);

        return { quad: result.quad, detected: result.detected };
    } finally {
        canvas.width = 0;
        canvas.height = 0;
    }
}

async function renderQuadFromSource(
    source: DecodedImageSource,
    quad: DocumentQuad,
    maxLongSide: number,
    quality: number,
    filter: ScanFilter,
    rotation: QuarterTurnRotation,
): Promise<{ jpegBytes: Uint8Array; width: number; height: number; previewUrl: string }> {
    const fullQuad = isFullQuad(quad);

    if (fullQuad && filter === 'original' && rotation === 0) {
        const { canvas, width, height } = drawScaledToCanvas(source, maxLongSide);
        try {
            const blob = await canvasToJpegBlob(canvas, quality);
            const jpegBytes = new Uint8Array(await blob.arrayBuffer());
            const previewUrl = URL.createObjectURL(blob);

            return { jpegBytes, width, height, previewUrl };
        } finally {
            canvas.width = 0;
            canvas.height = 0;
        }
    }

    const work = drawScaledToCanvas(source, fullQuad ? maxLongSide : MAX_WORKING_LONG_SIDE);
    const outCanvas = document.createElement('canvas');

    try {
        const workCtx = work.canvas.getContext('2d');
        if (!workCtx) {
            throw new Error('No fue posible leer los píxeles de la imagen original.');
        }

        const workImageData: ImageDataLike = workCtx.getImageData(0, 0, work.width, work.height);
        work.canvas.width = 0;
        work.canvas.height = 0;

        const baseImageData = fullQuad
            ? workImageData
            : warpPerspective(workImageData, quad, { maxLongSide });
        const rotated = rotation === 0 ? baseImageData : rotateImageData(baseImageData, rotation);
        const processed = filter === 'original' ? rotated : applyScanFilter(rotated, filter);

        outCanvas.width = processed.width;
        outCanvas.height = processed.height;
        const outCtx = outCanvas.getContext('2d');
        if (!outCtx) {
            throw new Error('No fue posible inicializar el lienzo de salida.');
        }

        const outputImageData = outCtx.createImageData(processed.width, processed.height);
        outputImageData.data.set(processed.data);
        outCtx.putImageData(outputImageData, 0, 0);

        const blob = await canvasToJpegBlob(outCanvas, quality);
        const jpegBytes = new Uint8Array(await blob.arrayBuffer());
        const previewUrl = URL.createObjectURL(blob);

        return {
            jpegBytes,
            width: processed.width,
            height: processed.height,
            previewUrl,
        };
    } finally {
        work.canvas.width = 0;
        work.canvas.height = 0;
        outCanvas.width = 0;
        outCanvas.height = 0;
    }
}

export async function addPageFromSource(
    source: Blob | File,
    options: AddPageFromSourceOptions = {},
): Promise<ScannedPageItem> {
    const maxLongSide = options.maxLongSide ?? DEFAULT_MAX_LONG_SIDE;
    const quality = options.quality ?? DEFAULT_JPEG_QUALITY;
    const filter = options.filter ?? DEFAULT_SCAN_FILTER;
    const rotation = normalizeRotation(options.rotation ?? 0);
    const sourceName = options.sourceName || (source instanceof File ? source.name : '') || 'pagina.jpg';
    const loaded = await loadImageSource(source, sourceName);
    const originalPreviewUrl = URL.createObjectURL(source);

    try {
        const detection = detectQuadFromSource(loaded);
        const rendered = await renderQuadFromSource(
            loaded,
            detection.quad,
            maxLongSide,
            quality,
            filter,
            rotation,
        );

        return {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
            jpegBytes: rendered.jpegBytes,
            width: rendered.width,
            height: rendered.height,
            previewUrl: rendered.previewUrl,
            sourceName,
            originalSource: source,
            originalPreviewUrl,
            originalWidth: loaded.width,
            originalHeight: loaded.height,
            quad: detection.quad,
            autoDetected: detection.detected,
            filter,
            rotation,
        };
    } catch (error) {
        URL.revokeObjectURL(originalPreviewUrl);
        throw error;
    } finally {
        loaded.cleanup();
    }
}

export async function encodeImageFileToScannedPage(
    file: File,
    options: EncodeImageOptions = {},
): Promise<ScannedPageItem> {
    return addPageFromSource(file, {
        ...options,
        sourceName: file.name || 'pagina.jpg',
    });
}

export async function reprocessScannedPage(
    page: ScannedPageItem,
    quad: DocumentQuad = page.quad,
    options: EncodeImageOptions = {},
): Promise<ScannedPageItem> {
    if (!isValidConvexQuad(quad)) {
        throw new Error('Las 4 esquinas deben formar un cuadrilátero convexo sin cruzarse.');
    }

    const maxLongSide = options.maxLongSide ?? DEFAULT_MAX_LONG_SIDE;
    const quality = options.quality ?? DEFAULT_JPEG_QUALITY;
    const nextFilter = options.filter ?? page.filter ?? DEFAULT_SCAN_FILTER;
    const nextRotation = options.rotation !== undefined
        ? normalizeRotation(options.rotation)
        : normalizeRotation(page.rotation ?? 0);
    const loaded = await loadImageSource(page.originalSource, page.sourceName);

    try {
        const nextQuad = cloneQuad(quad);
        const rendered = await renderQuadFromSource(
            loaded,
            nextQuad,
            maxLongSide,
            quality,
            nextFilter,
            nextRotation,
        );
        const previousPreviewUrl = page.previewUrl;

        if (previousPreviewUrl) {
            URL.revokeObjectURL(previousPreviewUrl);
        }

        return {
            ...page,
            jpegBytes: rendered.jpegBytes,
            width: rendered.width,
            height: rendered.height,
            previewUrl: rendered.previewUrl,
            quad: nextQuad,
            filter: nextFilter,
            rotation: nextRotation,
        };
    } finally {
        loaded.cleanup();
    }
}

export function revokeScannedPageUrls(page: Partial<ScannedPageItem> | null | undefined): void {
    if (!page) {
        return;
    }

    if (page.previewUrl) {
        URL.revokeObjectURL(page.previewUrl);
    }

    if (page.originalPreviewUrl) {
        URL.revokeObjectURL(page.originalPreviewUrl);
    }
}

export function stopMediaStream(
    stream: MediaStream | null | undefined,
    videoElement?: HTMLVideoElement | null,
): void {
    if (stream) {
        for (const track of stream.getTracks()) {
            try {
                track.stop();
            } catch {
                // Ignore tracks already stopped by the browser.
            }
        }
    }

    if (videoElement) {
        try {
            videoElement.pause();
        } catch {
            // Ignore pause errors on detached video elements.
        }
        videoElement.srcObject = null;
    }
}

export async function startRearCameraStream(): Promise<StartRearCameraResult> {
    const hasMediaDevices = typeof navigator !== 'undefined' && Boolean(navigator.mediaDevices);
    const hasGetUserMedia = hasMediaDevices && typeof navigator.mediaDevices.getUserMedia === 'function';
    const isSecure = typeof window !== 'undefined' && Boolean(window.isSecureContext);

    const environment = assessCameraEnvironment({
        isSecureContext: isSecure,
        hasMediaDevices,
        hasGetUserMedia,
    });

    if (!environment.supported) {
        return {
            ok: false,
            message: environment.message ?? 'El visor continuo de cámara no está disponible.',
        };
    }

    try {
        const stream = await navigator.mediaDevices.getUserMedia(buildRearCameraConstraints());
        const [track] = stream.getVideoTracks();

        if (!track) {
            stopMediaStream(stream);

            return {
                ok: false,
                message: mapCameraErrorToSpanish({ name: 'NotFoundError' }).message,
            };
        }

        const capabilities = typeof track.getCapabilities === 'function'
            ? (track.getCapabilities() as { torch?: unknown })
            : null;

        return {
            ok: true,
            stream,
            track,
            torchSupported: supportsTrackTorch(capabilities),
        };
    } catch (error) {
        return {
            ok: false,
            message: mapCameraErrorToSpanish(error).message,
        };
    }
}

export async function setTrackTorch(
    track: MediaStreamTrack | null | undefined,
    enabled: boolean,
): Promise<boolean> {
    if (!track || typeof track.applyConstraints !== 'function') {
        throw new Error('La linterna no es compatible con este dispositivo.');
    }

    await track.applyConstraints(buildTorchConstraints(enabled) as unknown as MediaTrackConstraints);

    return enabled;
}

export async function captureVideoFrameBlob(videoElement: HTMLVideoElement | null | undefined): Promise<Blob> {
    if (!videoElement || videoElement.videoWidth <= 0 || videoElement.videoHeight <= 0) {
        throw new Error('La cámara aún está enfocando. Intenta capturar nuevamente.');
    }

    const canvas = document.createElement('canvas');

    try {
        canvas.width = videoElement.videoWidth;
        canvas.height = videoElement.videoHeight;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('No fue posible capturar el cuadro de la cámara.');
        }

        ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

        return await canvasToJpegBlob(canvas, CAPTURE_FRAME_JPEG_QUALITY);
    } finally {
        canvas.width = 0;
        canvas.height = 0;
    }
}

export function buildScannedPdfFile(
    pages: ReadonlyArray<PdfJpegPage>,
    rawFilename: string,
    fallbackOptions: SuggestedFilenameOptions = {},
): { file: File; byteLength: number; filename: string } {
    const filename = ensurePdfFilename(rawFilename, fallbackOptions);
    const pdfBytes = buildPdfFromJpegPages(pages);
    const validation = validatePdfByteSize(pdfBytes.byteLength);

    if (!validation.valid) {
        if (validation.exceedsLimit) {
            throw new Error(
                `El PDF generado (${validation.formattedSize}) supera el límite permitido de ${validation.formattedMax}. Elimina algunas páginas antes de adjuntar.`,
            );
        }

        throw new Error('No fue posible generar un PDF válido con las páginas actuales.');
    }

    const blobPart = new Uint8Array(pdfBytes);
    const file = new File([blobPart], filename, { type: 'application/pdf' });

    return {
        file,
        byteLength: pdfBytes.byteLength,
        filename,
    };
}

export async function injectPdfIntoTransparencyUpload(file: File): Promise<void> {
    const pondRoot = document.querySelector('[data-transparency-documents-upload] .filepond--root');
    const filePondApi = (window as unknown as {
        FilePond?: { find?: (el: Element | null) => { addFile: (file: File) => Promise<unknown> } | null };
    }).FilePond;

    const pond = filePondApi?.find?.(pondRoot);

    if (!pond) {
        throw new Error('No se encontró el cargador de Documentos Adjuntos. Recarga la página e intenta nuevamente.');
    }

    await pond.addFile(file);
}

export function triggerLocalPdfDownload(file: File): void {
    const url = URL.createObjectURL(file);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = file.name;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

if (typeof window !== 'undefined') {
    (window as unknown as Record<string, unknown>).transparencyScanner = {
        MAX_PDF_BYTES,
        FULL_DOCUMENT_QUAD,
        DEFAULT_SCAN_FILTER,
        buildSuggestedPdfFilename,
        ensurePdfFilename,
        estimatePdfByteLength,
        formatByteSize,
        validatePdfByteSize,
        cloneQuad,
        isValidConvexQuad,
        detectDocumentQuad,
        warpPerspective,
        applyScanFilter,
        rotateImageData,
        normalizeRotation,
        movePage,
        addPageFromSource,
        encodeImageFileToScannedPage,
        reprocessScannedPage,
        revokeScannedPageUrls,
        startRearCameraStream,
        setTrackTorch,
        stopMediaStream,
        captureVideoFrameBlob,
        buildScannedPdfFile,
        injectPdfIntoTransparencyUpload,
        triggerLocalPdfDownload,
    };
}
