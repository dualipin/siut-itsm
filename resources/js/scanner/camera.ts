export type CameraFallbackReason = 'insecure-context' | 'unsupported';

export type CameraErrorCode =
    | 'permission-denied'
    | 'not-found'
    | 'not-readable'
    | 'overconstrained'
    | 'security'
    | 'unknown';

export interface CameraEnvironmentInput {
    isSecureContext?: boolean;
    hasMediaDevices?: boolean;
    hasGetUserMedia?: boolean;
}

export interface CameraEnvironmentAssessment {
    supported: boolean;
    reason: CameraFallbackReason | null;
    message: string | null;
}

export interface RearCameraConstraintOptions {
    idealWidth?: number;
    idealHeight?: number;
}

export interface TorchCapabilityShape {
    torch?: unknown;
}

export interface TorchConstraintSet {
    advanced: Array<{ torch: boolean }>;
}

export interface CameraErrorMapping {
    code: CameraErrorCode;
    message: string;
}

const DEFAULT_IDEAL_WIDTH = 1920;
const DEFAULT_IDEAL_HEIGHT = 1080;

export function assessCameraEnvironment(env: CameraEnvironmentInput): CameraEnvironmentAssessment {
    if (!env.isSecureContext) {
        return {
            supported: false,
            reason: 'insecure-context',
            message: 'La cámara continua requiere conexión segura (HTTPS o localhost). Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
        };
    }

    if (!env.hasMediaDevices || !env.hasGetUserMedia) {
        return {
            supported: false,
            reason: 'unsupported',
            message: 'Este navegador no soporta el visor de cámara en vivo. Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
        };
    }

    return {
        supported: true,
        reason: null,
        message: null,
    };
}

export function buildRearCameraConstraints(options: RearCameraConstraintOptions = {}): MediaStreamConstraints {
    const idealWidth = options.idealWidth ?? DEFAULT_IDEAL_WIDTH;
    const idealHeight = options.idealHeight ?? DEFAULT_IDEAL_HEIGHT;

    return {
        audio: false,
        video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: idealWidth },
            height: { ideal: idealHeight },
        },
    };
}

export function supportsTrackTorch(capabilities: TorchCapabilityShape | null | undefined): boolean {
    return capabilities?.torch === true;
}

export function buildTorchConstraints(enabled: boolean): TorchConstraintSet {
    return {
        advanced: [{ torch: Boolean(enabled) }],
    };
}

export function mapCameraErrorToSpanish(error: unknown): CameraErrorMapping {
    const name = typeof error === 'object' && error !== null && 'name' in error
        ? String((error as { name?: unknown }).name ?? '')
        : '';

    switch (name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
            return {
                code: 'permission-denied',
                message: 'No se otorgó permiso para usar la cámara. Puedes usar "Tomar foto" o "Seleccionar de galería" para agregar páginas.',
            };
        case 'NotFoundError':
        case 'DevicesNotFoundError':
            return {
                code: 'not-found',
                message: 'No se detectó una cámara disponible en este dispositivo. Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
            };
        case 'NotReadableError':
        case 'TrackStartError':
        case 'AbortError':
            return {
                code: 'not-readable',
                message: 'La cámara está ocupada por otra aplicación o no pudo iniciarse. Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
            };
        case 'OverconstrainedError':
        case 'ConstraintNotSatisfiedError':
            return {
                code: 'overconstrained',
                message: 'La cámara del dispositivo no aceptó la configuración solicitada. Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
            };
        case 'SecurityError':
            return {
                code: 'security',
                message: 'El navegador bloqueó el acceso a la cámara por contexto de seguridad. Usa "Tomar foto" o "Seleccionar de galería" para continuar.',
            };
        default:
            return {
                code: 'unknown',
                message: 'No fue posible abrir el visor continuo de cámara. Usa "Tomar foto" o "Seleccionar de galería" para agregar páginas.',
            };
    }
}
