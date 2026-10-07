<script setup lang="ts">
import { computed, ref } from 'vue'

const props = defineProps<{
    annualPetition: {
        id: number
        year: number
        deadline: string | null
        submissionUrl: string
    }
    convocations: Array<{
        id: number
        name: string
        sortOrder: number
        file: {
            id: number
            name: string
            mimeType: string
            size: number
            downloadUrl: string
        } | null
    }>
    maxProposals: number
}>()

const isClosed = computed(() => {
    if (!props.annualPetition.deadline) return false
    const [day, month, year] = props.annualPetition.deadline.split('/').map(Number)
    const deadlineDate = new Date(year, month - 1, day, 23, 59, 59)
    return new Date() > deadlineDate
})

interface Proposal {
    proposal: string
    file: File | null
}

const name = ref('')
const curp = ref('')
const proposals = ref<Proposal[]>([{ proposal: '', file: null }])
const fieldErrors = ref<Record<string, string>>({})
const formError = ref('')
const processing = ref(false)
const successMessage = ref('')

const canAddProposal = computed(() => proposals.value.length < props.maxProposals)

const csrfToken = (): string => {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null
    return meta?.content ?? ''
}

const handleFileUpload = (index: number, event: Event) => {
    const input = event.target as HTMLInputElement
    proposals.value[index].file = input.files?.[0] ?? null
}

const addProposal = () => {
    if (canAddProposal.value) {
        proposals.value.push({ proposal: '', file: null })
    }
}

const removeProposal = (index: number) => {
    if (proposals.value.length > 1) {
        proposals.value.splice(index, 1)
    }
}

const errorFor = (key: string): string | undefined => fieldErrors.value[key]

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const submit = async () => {
    processing.value = true
    fieldErrors.value = {}
    formError.value = ''
    successMessage.value = ''

    const formData = new FormData()
    formData.append('curp', curp.value.trim().toUpperCase())
    formData.append('agremiado_name', name.value.trim())

    proposals.value.forEach((item, index) => {
        formData.append(`proposals[${index}][proposal]`, item.proposal)
        if (item.file) {
            formData.append(`proposals[${index}][file]`, item.file)
        }
    })

    try {
        const response = await fetch(props.annualPetition.submissionUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        })

        const payload = await response.json().catch(() => ({}))

        if (!response.ok) {
            if (response.status === 422 && payload.errors) {
                fieldErrors.value = Object.fromEntries(
                    Object.entries(payload.errors as Record<string, string[]>).map(([key, messages]) => [
                        key,
                        messages[0],
                    ])
                )
            }
            formError.value = payload.message ?? 'Ocurrió un error al registrar sus peticiones.'
            return
        }

        successMessage.value = payload.message ?? 'Peticiones registradas correctamente.'

        if (payload.pdf_url) {
            window.open(payload.pdf_url, '_blank', 'noopener')
        }

        curp.value = ''
        name.value = ''
        proposals.value = [{ proposal: '', file: null }]
    } catch {
        formError.value = 'Error de conexión. Verifique su red e inténtelo nuevamente.'
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <div class="min-h-screen min-w-0 w-full flex flex-col overflow-x-hidden bg-base-200">

        <!-- ===== Franja institucional superior (tokens del tema) ===== -->
        <header class="bg-base-300 border-b border-base-300">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-6 sm:py-10">
                <div class="flex items-center gap-2 mb-2 sm:mb-4">
                    <span class="text-[10px] sm:text-[11px] uppercase tracking-[.18em] opacity-60 font-semibold">
                        Pliego anual de propuestas
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-semibold tracking-tight text-base-content">
                    Ejercicio {{ annualPetition.year }}
                </h1>
                <div class="mt-3 sm:mt-4 flex flex-wrap items-center gap-2.5 sm:gap-3 text-xs sm:text-sm">
                    <span :class="[
                        'badge badge-sm sm:badge-md badge-outline gap-1.5 sm:gap-2 py-2.5 sm:py-3',
                        isClosed ? 'badge-error' : 'badge-primary'
                    ]">
                        <span class="w-1.5 h-1.5 rounded-full" :class="isClosed ? 'bg-error' : 'bg-primary'"></span>
                        {{ isClosed ? 'Proceso cerrado' : 'Proceso abierto' }}
                    </span>
                    <span v-if="annualPetition.deadline" class="opacity-70">
                        Fecha límite:
                        <time class="font-semibold text-base-content">{{ annualPetition.deadline }}</time>
                    </span>
                </div>
            </div>
        </header>

        <!-- ===== Barra meta ===== -->
        <div class="bg-base-100 border-b border-base-300">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-2.5 sm:py-3 flex items-center justify-between text-xs opacity-60">
                <span>Propuestas: <strong class="text-base-content">{{ proposals.length }} de {{ maxProposals
                        }}</strong></span>
            </div>
        </div>

        <main class="flex-1">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-6 sm:py-10 space-y-6 sm:space-y-10">

                <div v-if="isClosed" class="alert alert-error rounded-box border-2 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <svg class="w-5 h-5 shrink-0 mt-0.5 sm:mt-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <div>
                        <h3 class="font-semibold text-sm sm:text-base">Proceso de recepción cerrado</h3>
                        <p class="text-xs sm:text-sm opacity-80 mt-0.5 sm:mt-1">
                            La fecha límite para registrar propuestas ({{ annualPetition.deadline }}) ya ha vencido.
                            No es posible enviar nuevas peticiones para este ejercicio.
                        </p>
                    </div>
                </div>

                <div v-if="successMessage" role="status" class="alert alert-success rounded-box flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path d="M9 12l2 2 4-4" />
                        <circle cx="12" cy="12" r="10" />
                    </svg>
                    <span class="text-xs sm:text-sm">{{ successMessage }} El acuse se abrió en una pestaña nueva.</span>
                </div>

                <div v-if="formError" role="alert" class="alert alert-error rounded-box flex items-center gap-3">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" />
                        <line x1="12" y1="8" x2="12" />
                        <line x1="12" y1="16" x2="12.01" y2="16" />
                    </svg>
                    <span class="text-xs sm:text-sm">{{ formError }}</span>
                </div>

                <!-- ===== 01 · Documentación base ===== -->
                <section v-if="convocations.length > 0">
                    <h2 class="text-[.7rem] uppercase font-semibold tracking-[.14em] opacity-50 mb-3">
                        01 — Convocatoria
                    </h2>
                    <div class="border-t border-base-300 pt-4 space-y-4">
                        <p class="text-xs sm:text-sm opacity-70">
                            Consulte las convocatorias vigentes antes de registrar sus propuestas.
                        </p>

                        <!-- Tabla para pantallas medianas/grandes (sm+) -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-sm">
                                <tbody>
                                    <tr v-for="convocation in convocations" :key="convocation.id"
                                        class="border-b border-base-300">
                                        <td class="py-3 pr-4 font-medium w-1/3">{{ convocation.name }}</td>
                                        <td class="py-3 pr-4 opacity-60" v-if="convocation.file">
                                            {{ convocation.file.name }} ({{ formatFileSize(convocation.file.size) }})
                                        </td>
                                        <td class="py-3 pr-4 opacity-40" v-else>—</td>
                                        <td class="py-3 text-right">
                                            <a v-if="convocation.file" :href="convocation.file.downloadUrl" download
                                                class="link link-primary font-medium no-underline hover:underline underline-offset-4">Descargar</a>
                                            <span v-else class="text-xs opacity-40">Sin archivo</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Vista en tarjetas para pantallas pequeñas/móviles (< sm) -->
                        <div class="sm:hidden space-y-3">
                            <div v-for="convocation in convocations" :key="convocation.id"
                                class="bg-base-100 border border-base-300 rounded-lg p-3 space-y-2">
                                <div class="font-medium text-xs text-base-content">{{ convocation.name }}</div>
                                <div class="flex items-center justify-between text-xs pt-2 border-t border-base-200 gap-2">
                                    <span v-if="convocation.file" class="opacity-60 truncate max-w-[65%]">
                                        {{ convocation.file.name }} ({{ formatFileSize(convocation.file.size) }})
                                    </span>
                                    <span v-else class="opacity-40">Sin archivo</span>

                                    <a v-if="convocation.file" :href="convocation.file.downloadUrl" download
                                        class="btn btn-xs btn-outline btn-primary shrink-0">Descargar</a>
                                </div>
                            </div>
                        </div>

                        <div class="bg-base-100/60 p-3.5 sm:p-4 rounded-lg border border-base-300/60 text-xs sm:text-sm opacity-80 leading-relaxed text-justify sm:text-left space-y-2">
                            <p>
                                Como parte de mis derechos laborales, consagrados en la Ley de los Trabajadores al Servicio
                                del Estado y de manera supletoria en la Ley Federal del Trabajo, así como los de la
                                Constitución Política de los Estados Unidos Mexicanos y demás relativos de Ley en materia
                                laboral, vengo ante usted a solicitar sea atendido(a) en los siguientes puntos, los cuales
                                muestran una violación a mis derechos laborales para que los atienda en su ámbito de
                                competencia, dentro de la relación laboral colectiva, que usted tiene manera legal con el
                                patronal o de manera jurídica, consistente en las quejas de agravio, así como también los
                                temas salariales y/o de prestaciones, que en este momento estaremos en proceso de revisión
                                de ante el emplazamiento a Huelga para este año con miras a darle solución a los siguientes
                                puntos los cuales solicito en dicho pliego de peticiones:
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ===== 02 · Identificación ===== -->
                <section>
                    <h2 class="text-[.7rem] uppercase font-semibold tracking-[.14em] opacity-50 mb-3">
                        02 — Identificación del agremiado
                    </h2>
                    <div class="border-t border-base-300 pt-4 sm:pt-6 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                        <div class="form-control">
                            <label class="label py-1" for="agremiado-name">
                                <span class="label-text text-xs uppercase tracking-wider opacity-60 font-semibold">
                                    Nombre completo
                                </span>
                            </label>
                            <input id="agremiado-name" v-model="name" type="text" autocomplete="name" maxlength="255"
                                placeholder="—" class="input input-bordered w-full text-sm min-h-[42px]"
                                :class="{ 'input-error': errorFor('agremiado_name') }"
                                :aria-invalid="Boolean(errorFor('agremiado_name'))"
                                :aria-describedby="errorFor('agremiado_name') ? 'agremiado-name-error' : undefined"
                                :disabled="isClosed" />
                            <p v-if="errorFor('agremiado_name')" id="agremiado-name-error"
                                class="label-text-alt text-error mt-1 text-xs">
                                {{ errorFor('agremiado_name') }}
                            </p>
                        </div>

                        <div class="form-control">
                            <label class="label py-1" for="curp">
                                <span
                                    class="label-text text-xs uppercase tracking-wider opacity-60 font-semibold">CURP</span>
                            </label>
                            <input id="curp" v-model="curp" type="text" inputmode="text" maxlength="18"
                                autocomplete="off" placeholder="18 caracteres" required
                                class="input input-bordered w-full font-mono text-sm min-h-[42px] uppercase"
                                :class="{ 'input-error': errorFor('curp') }" :aria-invalid="Boolean(errorFor('curp'))"
                                :aria-describedby="errorFor('curp') ? 'curp-error' : undefined"
                                :disabled="isClosed" />
                            <p v-if="errorFor('curp')" id="curp-error" class="label-text-alt text-error mt-1 text-xs">
                                {{ errorFor('curp') }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ===== 03 · Propuestas ===== -->
                <section>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-[.7rem] uppercase font-semibold tracking-[.14em] opacity-50">
                            03 — Registro de propuestas
                        </h2>
                        <span class="text-xs opacity-50 font-medium">{{ proposals.length }} de {{ maxProposals }}</span>
                    </div>
                    <div class="border-t border-base-300 pt-4 sm:pt-6 space-y-4 sm:space-y-6">

                        <fieldset v-for="(item, index) in proposals" :key="index"
                            class="min-w-0 bg-base-100 border border-base-300 rounded-box overflow-hidden shadow-xs">
                            <div
                                class="flex items-center justify-between px-4 sm:px-5 py-2.5 sm:py-3 border-b border-base-300 bg-base-200/60">
                                <legend class="text-xs font-semibold uppercase tracking-widest opacity-70">
                                    Propuesta {{ index + 1 }}
                                </legend>
                                <button v-if="proposals.length > 1 && !isClosed" type="button"
                                    class="text-xs text-error link link-hover font-medium no-underline py-1 px-2 rounded hover:bg-error/10 transition-colors"
                                    @click="removeProposal(index)">
                                    Eliminar
                                </button>
                            </div>
                            <div class="p-4 sm:p-5 space-y-4 sm:space-y-5">
                                <div class="form-control">
                                    <label class="label py-1" :for="`proposal-${index}`">
                                        <span
                                            class="label-text text-xs uppercase tracking-wider opacity-60 font-semibold">
                                            Descripción
                                        </span>
                                    </label>
                                    <textarea :id="`proposal-${index}`" v-model="item.proposal" rows="3"
                                        maxlength="5000" required class="textarea textarea-bordered w-full text-sm leading-relaxed"
                                        :class="{ 'textarea-error': errorFor(`proposals.${index}.proposal`) }"
                                        :aria-invalid="Boolean(errorFor(`proposals.${index}.proposal`))"
                                        :disabled="isClosed"></textarea>
                                    <p v-if="errorFor(`proposals.${index}.proposal`)"
                                        class="label-text-alt text-error mt-1 text-xs">
                                        {{ errorFor(`proposals.${index}.proposal`) }}
                                    </p>
                                </div>

                                <div class="form-control">
                                    <label class="label py-1" :for="`file-${index}`">
                                        <span
                                            class="label-text text-xs uppercase tracking-wider opacity-60 font-semibold">
                                            Archivo adjunto <span
                                                class="opacity-50 normal-case font-normal">(opcional)</span>
                                        </span>
                                    </label>
                                    <input :id="`file-${index}`" type="file" accept=".pdf,.jpg,.jpeg,.png"
                                        class="file-input file-input-bordered file-input-sm sm:file-input-md w-full text-xs sm:text-sm max-w-full"
                                        @change="(event) => handleFileUpload(index, event)"
                                        :disabled="isClosed" />
                                    <p class="label-text-alt opacity-50 mt-1.5 text-xs">PDF, JPG, JPEG o PNG. Máximo 10 MB.</p>
                                    <p v-if="item.file" class="text-xs text-success font-medium mt-1 truncate">
                                        ✓ Adjuntado: {{ item.file.name }}
                                    </p>
                                </div>
                            </div>
                        </fieldset>

                        <button v-if="canAddProposal && !isClosed" type="button"
                            class="btn btn-ghost btn-sm text-primary hover:bg-primary/10 gap-1.5 font-medium inline-flex items-center"
                            @click="addProposal">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                            Agregar otra propuesta
                        </button>
                    </div>
                </section>

                <!-- ===== Envío ===== -->
                <section class="border-t-2 border-base-content/80 pt-6 space-y-4">
                    <p class="text-xs opacity-60 leading-relaxed">
                        Al enviar, cada propuesta se registrará por separado y recibirá su propio acuse de recepción.
                    </p>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto sm:px-10 min-h-[44px] text-sm sm:text-base font-medium" :disabled="processing || isClosed"
                        @click.prevent="submit">
                        <span v-if="processing" class="loading loading-spinner loading-xs" aria-hidden="true"></span>
                        {{ processing ? 'Enviando peticiones…' : (isClosed ? 'Proceso cerrado' : 'Enviar peticiones') }}
                    </button>
                </section>

            </div>
        </main>

        <footer class="border-t border-base-300 mt-8 bg-base-100">
            <div class="max-w-3xl w-full min-w-0 mx-auto px-4 sm:px-6 py-4 sm:py-6 text-xs opacity-50 flex justify-between items-center">
                <span>OST SIUT ITSM</span>
                <span>{{ annualPetition.year }}</span>
            </div>
        </footer>
    </div>
</template>