import QRCodeStyling, { type Options } from 'qr-code-styling';

export interface QrCompositionConfig {
    width: number;
    height: number;
    qrSize: number;
    qrY: number;
    footerHeight: number;
}

export interface BuildQrOptions {
    brandLogoUrl?: string;
    brandFooterText?: string;
    brandTitleText?: string;
    primaryColor?: string;
    bodyBg?: string;
    footerTextColor?: string;
    borderColor?: string;
    composition?: Partial<QrCompositionConfig>;
}

export const DEFAULT_COMPOSITION: QrCompositionConfig = {
    width: 800,
    height: 1000,
    qrSize: 600,
    qrY: 140,
    footerHeight: 120,
};

/**
 * Convierte un Blob binario a una instancia de HTMLImageElement.
 */
export function loadImageFromBlob(blob: Blob): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(blob);
        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(img);
        };
        img.onerror = (error) => {
            URL.revokeObjectURL(objectUrl);
            reject(error);
        };
        img.src = objectUrl;
    });
}

/**
 * Obtiene el valor computado de una variable CSS o devuelve un fallback.
 */
export function getCssVar(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }
    const val = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return val || fallback;
}

/**
 * Construye y renderiza un elemento Canvas con el código QR estilizado,
 * logotipo central y pie de página institucional del sindicato.
 */
export async function buildQrCanvas(url: string, options: BuildQrOptions = {}): Promise<HTMLCanvasElement> {
    const composition: QrCompositionConfig = {
        ...DEFAULT_COMPOSITION,
        ...(options.composition || {}),
    };

    const primaryColor = options.primaryColor
        || getCssVar('--siut-color-primary', getCssVar('--color-primary', '#611232'));
    const bodyBg = options.bodyBg || '#ffffff';
    const footerTextColor = options.footerTextColor || '#ffffff';
    const borderColor = options.borderColor || '#e2e8f0';
    const brandFooterText = options.brandFooterText || 'OST SIUT ITSM';
    const brandLogoUrl = options.brandLogoUrl || '/assets/img/logo.webp';

    const outputCanvas = document.createElement('canvas');
    outputCanvas.width = composition.width;
    outputCanvas.height = composition.height;

    const context = outputCanvas.getContext('2d');
    if (!context) {
        throw new Error('No se pudo obtener el contexto 2D del Canvas.');
    }

    // 1. Fondo general del lienzo
    context.fillStyle = bodyBg;
    context.fillRect(0, 0, composition.width, composition.height);

    // 2. Encabezado institucional decorativo sutil
    if (options.brandTitleText) {
        context.fillStyle = primaryColor;
        context.font = '700 24px Spline Sans, Inter, sans-serif';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(options.brandTitleText, composition.width / 2, composition.qrY / 2);
    }

    // 3. Resolución completa del Logo para evitar problemas de CORS y origen relativo
    const fullLogoUrl = brandLogoUrl.startsWith('http')
        ? brandLogoUrl
        : new URL(brandLogoUrl, window.location.origin).toString();

    // 4. Configuración del generador QR con qr-code-styling
    const qrOptions: Options = {
        width: composition.qrSize,
        height: composition.qrSize,
        type: 'canvas',
        data: url,
        image: fullLogoUrl,
        qrOptions: {
            errorCorrectionLevel: 'H', // Nivel alto para soportar el logo en el centro
        },
        imageOptions: {
            crossOrigin: 'anonymous',
            margin: 6,
            imageSize: 0.75,
        },
        dotsOptions: {
            color: primaryColor,
            type: 'rounded',
        },
        cornersSquareOptions: {
            color: primaryColor,
            type: 'extra-rounded',
        },
        cornersDotOptions: {
            color: primaryColor,
            type: 'dot',
        },
        backgroundOptions: {
            color: '#ffffff',
        },
    };

    const qrGenerator = new QRCodeStyling(qrOptions);
    const qrRawData = await qrGenerator.getRawData('png');

    if (!qrRawData) {
        throw new Error('Error al generar el buffer de imagen del código QR.');
    }

    const qrBlob = qrRawData instanceof Blob ? qrRawData : new Blob([qrRawData], { type: 'image/png' });
    const qrImage = await loadImageFromBlob(qrBlob);

    // 5. Dibujar contenedor QR centrado con marco
    const qrX = (composition.width - composition.qrSize) / 2;
    context.fillStyle = '#ffffff';
    context.fillRect(qrX - 14, composition.qrY - 14, composition.qrSize + 28, composition.qrSize + 28);

    context.strokeStyle = borderColor;
    context.lineWidth = 2;
    context.strokeRect(qrX - 14, composition.qrY - 14, composition.qrSize + 28, composition.qrSize + 28);

    // Dibujar el QR generado
    context.drawImage(qrImage, qrX, composition.qrY, composition.qrSize, composition.qrSize);

    // 6. Pie de página institucional
    const footerY = composition.height - composition.footerHeight;
    context.fillStyle = primaryColor;
    context.fillRect(0, footerY, composition.width, composition.footerHeight);

    context.fillStyle = footerTextColor;
    context.font = '700 42px Spline Sans, Inter, sans-serif';
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(brandFooterText, composition.width / 2, footerY + composition.footerHeight / 2);

    return outputCanvas;
}

/**
 * Dispara la descarga de un canvas como imagen PNG en el navegador.
 */
export function downloadQrCanvas(canvas: HTMLCanvasElement, filename = 'qr-institucional-siut.png'): void {
    const link = document.createElement('a');
    link.download = filename.endsWith('.png') ? filename : `${filename}.png`;
    link.href = canvas.toDataURL('image/png');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
