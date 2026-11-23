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
    assessCameraEnvironment,
    buildRearCameraConstraints,
    buildTorchConstraints,
    mapCameraErrorToSpanish,
    supportsTrackTorch,
} from './scanner/camera.ts';

export interface ScannedPageItem extends PdfJpegPage {
    id: string;
    previewUrl: string;
    sourceName: string;
}

export interface EncodeImageOptions {
    maxLongSide?: number;
    quality?: number;
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

const DEFAULT_MAX_LONG_SIDE = 1600;
const DEFAULT_JPEG_QUALITY = 0.78;
const CAPTURE_FRAME_JPEG_QUALITY = 0.92;

async function loadImageSource(
    source: Blob,
    sourceName: string,
): Promise<{
    width: number;
    height: number;
    draw: (ctx: CanvasRenderingContext2D, targetWidth: number, targetHeight: number) => void;
    cleanup: () => void;
}> {
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

export async function addPageFromSource(
    source: Blob | File,
    options: AddPageFromSourceOptions = {},
): Promise<ScannedPageItem> {
    const maxLongSide = options.maxLongSide ?? DEFAULT_MAX_LONG_SIDE;
    const quality = options.quality ?? DEFAULT_JPEG_QUALITY;
    const sourceName = options.sourceName || (source instanceof File ? source.name : '') || 'pagina.jpg';
    const loaded = await loadImageSource(source, sourceName);

    const canvas = document.createElement('canvas');

    try {
        const longSide = Math.max(loaded.width, loaded.height);
        const scale = longSide > maxLongSide ? maxLongSide / longSide : 1;
        const targetWidth = Math.max(1, Math.round(loaded.width * scale));
        const targetHeight = Math.max(1, Math.round(loaded.height * scale));

        canvas.width = targetWidth;
        canvas.height = targetHeight;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
            throw new Error('No fue posible inicializar el lienzo de procesamiento de imagen.');
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, targetWidth, targetHeight);
        loaded.draw(ctx, targetWidth, targetHeight);

        const blob = await canvasToJpegBlob(canvas, quality);
        const jpegBytes = new Uint8Array(await blob.arrayBuffer());
        const previewUrl = URL.createObjectURL(blob);

        return {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`,
            jpegBytes,
            width: targetWidth,
            height: targetHeight,
            previewUrl,
            sourceName,
        };
    } finally {
        loaded.cleanup();
        canvas.width = 0;
        canvas.height = 0;
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
        buildSuggestedPdfFilename,
        ensurePdfFilename,
        estimatePdfByteLength,
        formatByteSize,
        validatePdfByteSize,
        addPageFromSource,
        encodeImageFileToScannedPage,
        startRearCameraStream,
        setTrackTorch,
        stopMediaStream,
        captureVideoFrameBlob,
        buildScannedPdfFile,
        injectPdfIntoTransparencyUpload,
        triggerLocalPdfDownload,
    };
}
