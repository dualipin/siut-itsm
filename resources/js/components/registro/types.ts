export type DocumentKind = 'ine' | 'nomina' | 'photo'

export type AIStatus = 'idle' | 'uploading' | 'processing' | 'success' | 'warning' | 'failed' | 'not_applicable'

export interface RegistrationProps {
    documentsUrl: string
    submitUrl: string
    loginUrl: string
    privacyUrl: string
    maxDocSize?: number
    maxPhotoSize?: number
}

export interface ExtractedIneFields {
    nombre?: string
    apellidos?: string
    curp?: string
    fecha_nacimiento?: string
    domicilio?: string
    vigencia?: string
}

export interface ExtractedNominaFields {
    nombre?: string
    nss?: string
    curp?: string
    rfc?: string
    fecha_nacimiento?: string
    fecha_ingreso?: string
    categoria?: string
    salario?: string
    periodo?: string
    fecha_pago?: string
}

export interface UploadedDocState {
    kind: DocumentKind
    file: File | null
    fileName: string
    fileSize: number
    previewUrl: string | null
    token: string | null
    aiStatus: AIStatus
    warnings: string[]
    fields: Record<string, any>
    uploading: boolean
    progress: number
    error: string | null
}

export interface RegistrationFormData {
    name: string
    surnames: string
    email: string
    phone: string
    birth_date: string
    curp: string
    nss: string
    category: string
    salary: string | number
    address: string
    password: string
    password_confirmation: string
    ine_token: string
    nomina_token: string
    photo_token: string
    [key: string]: any
}

export type FieldSource = 'ine' | 'nomina' | 'manual'
