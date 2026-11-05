<script setup lang="ts">
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useIslandForm } from '@/composables/useIslandForm'
import { useIslandUpload } from '@/composables/useIslandUpload'
import CredentialPreview from './credential-preview.vue'
import StepDocuments from './step-documents.vue'
import StepData from './step-data.vue'
import StepAccount from './step-account.vue'
import type { UploadedDocState } from './document-dropzone.vue'
import type { RegistrationFormData, FieldSource } from './step-data.vue'

interface RegistrationProps {
    documentsUrl: string
    submitUrl: string
    loginUrl: string
    privacyUrl: string
    maxDocSize?: number
    maxPhotoSize?: number
}

const props = defineProps<RegistrationProps>()

const currentStep = ref<1 | 2 | 3>(1)
const isScanning = ref(false)

const ineUpload = useIslandUpload()
const nominaUpload = useIslandUpload()
const photoUpload = useIslandUpload()

const ineState = reactive<UploadedDocState>({
    kind: 'ine',
    file: null,
    fileName: '',
    fileSize: 0,
    previewUrl: null,
    token: null,
    aiStatus: 'idle',
    warnings: [],
    fields: {},
    uploading: false,
    progress: 0,
    error: null,
})

const nominaState = reactive<UploadedDocState>({
    kind: 'nomina',
    file: null,
    fileName: '',
    fileSize: 0,
    previewUrl: null,
    token: null,
    aiStatus: 'idle',
    warnings: [],
    fields: {},
    uploading: false,
    progress: 0,
    error: null,
})

const photoState = reactive<UploadedDocState>({
    kind: 'photo',
    file: null,
    fileName: '',
    fileSize: 0,
    previewUrl: null,
    token: null,
    aiStatus: 'idle',
    warnings: [],
    fields: {},
    uploading: false,
    progress: 0,
    error: null,
})

const fieldSources = reactive<Record<string, FieldSource>>({})

const form = useIslandForm<RegistrationFormData>({
    name: '',
    surnames: '',
    email: '',
    phone: '',
    birth_date: '',
    curp: '',
    nss: '',
    category: '',
    salary: '',
    address: '',
    password: '',
    password_confirmation: '',
    ine_token: '',
    nomina_token: '',
    photo_token: '',
})

const isUploadingAny = computed(() => {
    return ineUpload.uploading.value || nominaUpload.uploading.value || photoUpload.uploading.value
})

function triggerScanEffect() {
    isScanning.value = true
    setTimeout(() => {
        isScanning.value = false
    }, 1400)
}

async function handleUploadFile(kind: 'ine' | 'nomina' | 'photo', file: File) {
    const uploadService = kind === 'ine' ? ineUpload : kind === 'nomina' ? nominaUpload : photoUpload
    const state = kind === 'ine' ? ineState : kind === 'nomina' ? nominaState : photoState

    state.file = file
    state.fileName = file.name
    state.fileSize = file.size
    state.uploading = true
    state.progress = 0
    state.error = null

    if (kind === 'photo') {
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl)
        state.previewUrl = URL.createObjectURL(file)
    }

    const res = await uploadService.upload(props.documentsUrl, file, { kind })

    state.uploading = false
    state.progress = 100

    if (res && res.token) {
        state.token = res.token
        state.aiStatus = (res.ai_status as any) || 'success'
        state.warnings = res.warnings || []
        state.fields = res.fields || {}

        if (kind === 'ine') {
            form.data.ine_token = res.token
            if (res.fields) {
                if (res.fields.nombre && !form.data.name) {
                    form.data.name = res.fields.nombre
                    fieldSources.name = 'ine'
                }
                if (res.fields.apellidos && !form.data.surnames) {
                    form.data.surnames = res.fields.apellidos
                    fieldSources.surnames = 'ine'
                }
                if (res.fields.curp && !form.data.curp) {
                    form.data.curp = res.fields.curp.toUpperCase()
                    fieldSources.curp = 'ine'
                }
                if (res.fields.fecha_nacimiento && !form.data.birth_date) {
                    form.data.birth_date = res.fields.fecha_nacimiento
                    fieldSources.birth_date = 'ine'
                }
                if (res.fields.domicilio && !form.data.address) {
                    form.data.address = res.fields.domicilio
                    fieldSources.address = 'ine'
                }
            }
            triggerScanEffect()
        } else if (kind === 'nomina') {
            form.data.nomina_token = res.token
            if (res.fields) {
                if (res.fields.nss && !form.data.nss) {
                    form.data.nss = res.fields.nss
                    fieldSources.nss = 'nomina'
                }
                if (res.fields.curp && !form.data.curp) {
                    form.data.curp = res.fields.curp.toUpperCase()
                    fieldSources.curp = 'nomina'
                }
                if (res.fields.fecha_nacimiento && !form.data.birth_date) {
                    form.data.birth_date = res.fields.fecha_nacimiento
                    fieldSources.birth_date = 'nomina'
                }
                if (res.fields.categoria && !form.data.category) {
                    form.data.category = res.fields.categoria
                    fieldSources.category = 'nomina'
                }
                if (res.fields.salario && !form.data.salary) {
                    form.data.salary = res.fields.salario
                    fieldSources.salary = 'nomina'
                }
            }
            triggerScanEffect()
        } else if (kind === 'photo') {
            form.data.photo_token = res.token
        }
    } else {
        state.error = uploadService.error.value || 'Error al procesar el archivo.'
    }
}

function handleRemoveFile(kind: 'ine' | 'nomina' | 'photo') {
    const uploadService = kind === 'ine' ? ineUpload : kind === 'nomina' ? nominaUpload : photoUpload
    const state = kind === 'ine' ? ineState : kind === 'nomina' ? nominaState : photoState

    uploadService.reset()
    state.file = null
    state.fileName = ''
    state.fileSize = 0
    state.token = null
    state.aiStatus = 'idle'
    state.warnings = []
    state.fields = {}
    state.error = null

    if (kind === 'photo') {
        if (state.previewUrl) URL.revokeObjectURL(state.previewUrl)
        state.previewUrl = null
        form.data.photo_token = ''
    } else if (kind === 'ine') {
        form.data.ine_token = ''
    } else if (kind === 'nomina') {
        form.data.nomina_token = ''
    }
}

function goToStep(step: 1 | 2 | 3) {
    currentStep.value = step
    nextTick(() => {
        const titleEl = document.getElementById(`step-${step}-title`)
        if (titleEl) {
            titleEl.focus()
            titleEl.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
    })
}

async function submitFinalForm() {
    await form.post(props.submitUrl, {
        onSuccess: (data: any) => {
            if (data?.redirect) {
                window.location.href = data.redirect
            }
        },
        onError: (errs) => {
            // Jump to first step containing error
            const s1Fields = ['ine_token', 'nomina_token', 'photo_token']
            const s2Fields = ['name', 'surnames', 'curp', 'birth_date', 'nss', 'phone', 'category', 'salary', 'address']
            if (s1Fields.some((f) => errs[f as keyof RegistrationFormData])) {
                goToStep(1)
            } else if (s2Fields.some((f) => errs[f as keyof RegistrationFormData])) {
                goToStep(2)
            }
        },
    })
}

function handleBeforeUnload(e: BeforeUnloadEvent) {
    const hasData = Boolean(
        form.data.ine_token ||
        form.data.name ||
        form.data.curp ||
        form.data.email
    )
    if (hasData && !form.recentlySuccessful) {
        e.preventDefault()
        e.returnValue = ''
    }
}

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload)
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload)
    if (photoState.previewUrl) URL.revokeObjectURL(photoState.previewUrl)
})
</script>

<template>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full min-w-0">
        <!-- Page Header -->
        <header class="text-center max-w-2xl mx-auto mb-6 sm:mb-8">
            <h1 class="text-3xl sm:text-4xl font-display font-black text-base-content tracking-tight">
                Afiliación Sindical en Línea
            </h1>
            <p class="text-sm sm:text-base text-base-content/70 mt-2">
                Completa tu solicitud oficial para integrarte al padrón del Sindicato Independiente de Trabajadores del ITSM.
            </p>
        </header>

        <!-- Stepper DaisyUI fijo: se mantiene visible mientras se recorre el formulario -->
        <nav
            aria-label="Progreso del registro"
            class="sticky rounded-2xl top-20 z-40 mb-6 border border-base-300/70 bg-base-100/90 backdrop-blur-md px-3 sm:px-6 py-2 shadow-xs w-full min-w-0"
        >
            <ul class="steps steps-horizontal w-full text-[11px] sm:text-xs font-semibold">
                <li class="step" :class="{ 'step-primary': currentStep >= 1 }" :aria-current="currentStep === 1 ? 'step' : undefined">Documentos</li>
                <li class="step" :class="{ 'step-primary': currentStep >= 2 }" :aria-current="currentStep === 2 ? 'step' : undefined">Tus Datos</li>
                <li class="step" :class="{ 'step-primary': currentStep >= 3 }" :aria-current="currentStep === 3 ? 'step' : undefined">Cuenta y Envío</li>
            </ul>
        </nav>

        <!-- Main Content Grid (Wizard + Sticky Credential) -->
        <div class="grid grid-cols-[minmax(0,1fr)] lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <!-- Form Steps Column -->
            <div class="min-w-0 w-full lg:col-span-7 bg-base-100 rounded-3xl p-4 sm:p-8 border border-base-300 shadow-sm overflow-hidden">
                <!-- Step 1: Documents -->
                <StepDocuments
                    v-if="currentStep === 1"
                    :ine-state="ineState"
                    :nomina-state="nominaState"
                    :photo-state="photoState"
                    :errors="form.errors"
                    :is-uploading-any="isUploadingAny"
                    @upload-file="handleUploadFile"
                    @remove-file="handleRemoveFile"
                    @cancel-upload="handleRemoveFile"
                    @next="goToStep(2)"
                />

                <!-- Step 2: Personal & Labor Data -->
                <StepData
                    v-else-if="currentStep === 2"
                    :form="form"
                    :field-sources="fieldSources"
                    @prev="goToStep(1)"
                    @next="goToStep(3)"
                />

                <!-- Step 3: Account Credentials & Submission -->
                <StepAccount
                    v-else-if="currentStep === 3"
                    :form="form"
                    :privacy-url="privacyUrl"
                    :login-url="loginUrl"
                    @prev="goToStep(2)"
                    @submit="submitFinalForm"
                />
            </div>

            <!-- Credential Live Preview Column -->
            <div class="min-w-0 lg:col-span-5 lg:sticky lg:top-44">
                <CredentialPreview
                    :name="form.data.name"
                    :surnames="form.data.surnames"
                    :curp="form.data.curp"
                    :nss="form.data.nss"
                    :category="form.data.category"
                    :photo-url="photoState.previewUrl"
                    :is-scanning="isScanning"
                />
            </div>
        </div>
    </div>
</template>
