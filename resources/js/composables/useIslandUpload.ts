import { ref, type Ref } from 'vue'

export interface UploadResponse<T = any> {
    token?: string
    fields?: Record<string, any>
    warnings?: string[]
    ai_status?: string
    [key: string]: any
}

export interface UseIslandUploadReturn {
    uploading: Ref<boolean>
    progress: Ref<number>
    error: Ref<string | null>
    errors: Ref<Record<string, string[]>>
    data: Ref<UploadResponse | null>
    upload: (url: string, file: File, extraFields?: Record<string, string>) => Promise<UploadResponse | null>
    cancel: () => void
    retry: () => Promise<UploadResponse | null>
    reset: () => void
}

export function useIslandUpload(): UseIslandUploadReturn {
    const uploading = ref(false)
    const progress = ref(0)
    const error = ref<string | null>(null)
    const errors = ref<Record<string, string[]>>({})
    const data = ref<UploadResponse | null>(null)

    let xhr: XMLHttpRequest | null = null
    let lastUrl = ''
    let lastFile: File | null = null
    let lastExtraFields: Record<string, string> | undefined

    function getCsrfToken(): string {
        if (typeof document === 'undefined') {
            return ''
        }
        const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
        if (meta?.content) {
            return meta.content
        }
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
        return match ? decodeURIComponent(match[1]) : ''
    }

    function cancel() {
        if (xhr) {
            xhr.abort()
            xhr = null
            uploading.value = false
            progress.value = 0
        }
    }

    function reset() {
        cancel()
        error.value = null
        errors.value = {}
        data.value = null
        progress.value = 0
    }

    function upload(
        url: string,
        file: File,
        extraFields?: Record<string, string>
    ): Promise<UploadResponse | null> {
        cancel()

        lastUrl = url
        lastFile = file
        lastExtraFields = extraFields

        uploading.value = true
        progress.value = 0
        error.value = null
        errors.value = {}
        data.value = null

        return new Promise((resolve) => {
            const formData = new FormData()
            formData.append('file', file)

            if (extraFields) {
                for (const [key, val] of Object.entries(extraFields)) {
                    formData.append(key, val)
                }
            }

            xhr = new XMLHttpRequest()
            xhr.open('POST', url, true)
            xhr.setRequestHeader('Accept', 'application/json')
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')

            const token = getCsrfToken()
            if (token) {
                xhr.setRequestHeader('X-CSRF-TOKEN', token)
            }

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100)
                    progress.value = percent
                }
            }

            xhr.onload = () => {
                uploading.value = false
                const status = xhr?.status ?? 0

                let json: any = null
                try {
                    json = JSON.parse(xhr?.responseText || '{}')
                } catch {
                    json = {}
                }

                if (status >= 200 && status < 300) {
                    data.value = json
                    resolve(json)
                } else if (status === 422) {
                    errors.value = json.errors || {}
                    const firstMsg = Object.values(errors.value)[0]?.[0]
                    error.value = firstMsg || json.message || 'Error de validación al procesar el archivo.'
                    resolve(null)
                } else if (status === 429) {
                    error.value = 'Límite de solicitudes alcanzado. Por favor espera un minuto antes de reintentar.'
                    resolve(null)
                } else {
                    error.value = json.message || `Error en el servidor (HTTP ${status}).`
                    resolve(null)
                }
            }

            xhr.onerror = () => {
                uploading.value = false
                error.value = 'Error de conexión de red al subir el archivo.'
                resolve(null)
            }

            xhr.onabort = () => {
                uploading.value = false
                resolve(null)
            }

            xhr.send(formData)
        })
    }

    function retry(): Promise<UploadResponse | null> {
        if (!lastUrl || !lastFile) {
            return Promise.resolve(null)
        }
        return upload(lastUrl, lastFile, lastExtraFields)
    }

    return {
        uploading,
        progress,
        error,
        errors,
        data,
        upload,
        cancel,
        retry,
        reset,
    }
}
