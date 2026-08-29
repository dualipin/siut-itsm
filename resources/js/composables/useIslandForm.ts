import { reactive } from 'vue'

export interface IslandFormOptions<T extends Record<string, any>> {
    onSuccess?: (data: any) => void
    onError?: (errors: Record<keyof T, string[]>) => void
    onFinish?: () => void
}

export interface IslandForm<T extends Record<string, any>> {
    data: T
    errors: Partial<Record<keyof T, string[]>>
    processing: boolean
    recentlySuccessful: boolean
    statusMessage: string
    statusType: 'success' | 'error' | ''
    clearErrors: () => void
    clearFeedback: () => void
    reset: () => void
    submit: (method: 'post' | 'put' | 'patch' | 'delete', url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    post: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    put: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    patch: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
    delete: (url: string, options?: IslandFormOptions<T>) => Promise<boolean>
}

export function useIslandForm<T extends Record<string, any>>(initialValues: T): IslandForm<T> {
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

    const form = reactive<IslandForm<T>>({
        data: { ...initialValues } as T,
        errors: {} as Partial<Record<keyof T, string[]>>,
        processing: false,
        recentlySuccessful: false,
        statusMessage: '',
        statusType: '',

        clearErrors() {
            form.errors = {}
        },

        clearFeedback() {
            form.statusMessage = ''
            form.statusType = ''
            form.recentlySuccessful = false
        },

        reset() {
            Object.assign(form.data, initialValues)
            form.errors = {}
        },

        async submit(
            method: 'post' | 'put' | 'patch' | 'delete',
            url: string,
            options?: IslandFormOptions<T>
        ): Promise<boolean> {
            form.processing = true
            form.recentlySuccessful = false
            form.clearErrors()
            form.clearFeedback()

            try {
                const response = await fetch(url, {
                    method: method.toUpperCase(),
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(form.data),
                })

                const responseData = await response.json().catch(() => ({}))

                if (response.ok) {
                    form.processing = false
                    form.recentlySuccessful = true
                    form.statusType = 'success'
                    form.statusMessage = responseData.message || 'Operación completada exitosamente.'

                    if (options?.onSuccess) {
                        options.onSuccess(responseData)
                    }

                    if (options?.onFinish) {
                        options.onFinish()
                    }

                    return true
                }

                // Validation error (HTTP 422)
                if (response.status === 422) {
                    form.processing = false
                    form.recentlySuccessful = false
                    form.statusType = 'error'
                    form.statusMessage = responseData.message || 'Por favor verifica los campos marcados.'
                    form.errors = responseData.errors || {}

                    if (options?.onError) {
                        options.onError(form.errors as Record<keyof T, string[]>)
                    }

                    if (options?.onFinish) {
                        options.onFinish()
                    }

                    return false
                }

                // Other HTTP errors (500, 403, 404, etc.)
                form.processing = false
                form.recentlySuccessful = false
                form.statusType = 'error'
                form.statusMessage = responseData.message || 'Ocurrió un error inesperado al procesar la solicitud.'

                if (options?.onFinish) {
                    options.onFinish()
                }

                return false
            } catch {
                form.processing = false
                form.recentlySuccessful = false
                form.statusType = 'error'
                form.statusMessage = 'Error de conexión. Por favor verifica tu red e inténtalo nuevamente.'

                if (options?.onFinish) {
                    options.onFinish()
                }

                return false
            }
        },

        post(url: string, options?: IslandFormOptions<T>) {
            return form.submit('post', url, options)
        },

        put(url: string, options?: IslandFormOptions<T>) {
            return form.submit('put', url, options)
        },

        patch(url: string, options?: IslandFormOptions<T>) {
            return form.submit('patch', url, options)
        },

        delete(url: string, options?: IslandFormOptions<T>) {
            return form.submit('delete', url, options)
        },
    }) as IslandForm<T>

    return form
}
