<script setup lang="ts">
import { ref } from 'vue'
import {
    UploadCloud,
    FileText,
    Image as ImageIcon,
    CheckCircle2,
    AlertTriangle,
    X,
    RotateCw,
    Loader2
} from '@lucide/vue'

export type DocumentKind = 'ine' | 'nomina' | 'photo'

export interface UploadedDocState {
    kind: DocumentKind
    file: File | null
    fileName: string
    fileSize: number
    previewUrl: string | null
    token: string | null
    aiStatus: string
    warnings: string[]
    fields: Record<string, any>
    uploading: boolean
    progress: number
    error: string | null
}

const props = withDefaults(
    defineProps<{
        kind: DocumentKind
        label: string
        helperText?: string
        accept?: string
        maxSizeMb?: number
        capture?: boolean | 'user' | 'environment'
        state: UploadedDocState
        errorMsg?: string | null
    }>(),
    {
        helperText: '',
        accept: 'application/pdf,image/jpeg,image/png,image/webp',
        maxSizeMb: 10,
        capture: undefined,
        errorMsg: null,
    }
)

const emit = defineEmits<{
    (e: 'file-selected', file: File): void
    (e: 'remove'): void
    (e: 'retry'): void
    (e: 'cancel'): void
}>()

const fileInputRef = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)

function openFileDialog() {
    fileInputRef.value?.click()
}

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement
    const file = target.files?.[0]
    if (file) {
        validateAndEmit(file)
    }
    target.value = ''
}

function handleDrop(event: DragEvent) {
    isDragging.value = false
    const file = event.dataTransfer?.files?.[0]
    if (file) {
        validateAndEmit(file)
    }
}

function validateAndEmit(file: File) {
    if (file.size > props.maxSizeMb * 1024 * 1024) {
        props.state.error = `El archivo pesa ${formatBytes(file.size)} y excede el límite máximo de ${props.maxSizeMb} MB.`
        props.state.token = null
        props.state.file = null
        return
    }
    props.state.error = null
    emit('file-selected', file)
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}
</script>

<template>
    <div class="w-full min-w-0">
        <label class="font-bold text-sm sm:text-base text-base-content flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1 mb-2 w-full">
            <span class="min-w-0 break-words">{{ label }}</span>
            <span class="text-xs font-normal text-base-content/60 shrink-0">Máx. {{ maxSizeMb }} MB</span>
        </label>

        <!-- Hidden Native File Input -->
        <input
            ref="fileInputRef"
            type="file"
            :accept="accept"
            :capture="capture"
            class="hidden"
            :aria-label="label"
            @change="handleFileChange"
        />

        <!-- In-Progress Upload State -->
        <div
            v-if="state.uploading"
            class="card card-border bg-base-100 rounded-2xl p-4 sm:p-5 shadow-xs w-full min-w-0"
        >
            <div class="flex items-center justify-between gap-3 mb-2 min-w-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <Loader2 class="size-5 text-primary animate-spin shrink-0" />
                    <span class="text-sm font-medium text-base-content truncate">
                        Subiendo {{ state.fileName || 'archivo' }}...
                    </span>
                </div>
                <span class="text-xs font-mono font-bold text-primary shrink-0">{{ state.progress }}%</span>
            </div>

            <!-- Progress Bar -->
            <progress class="progress progress-primary w-full" :value="state.progress" max="100"></progress>

            <div class="mt-3 flex justify-end">
                <button
                    type="button"
                    class="btn btn-ghost btn-xs text-error font-medium"
                    @click="emit('cancel')"
                >
                    Cancelar subida
                </button>
            </div>
        </div>

        <!-- Uploaded / Processed State (Requires valid token) -->
        <div
            v-else-if="state.token"
            class="card card-border bg-base-100 rounded-2xl p-3.5 sm:p-5 shadow-sm transition-all w-full max-w-full min-w-0 overflow-hidden"
        >
            <div class="flex items-center justify-between gap-2.5 sm:gap-3 w-full min-w-0">
                <!-- Preview / Icon + File Details -->
                <div class="flex flex-1 items-center gap-2.5 sm:gap-3 min-w-0">
                    <!-- Photo thumbnail or Document Icon -->
                    <div
                        v-if="kind === 'photo' && state.previewUrl"
                        class="size-11 sm:size-14 rounded-xl overflow-hidden border border-base-300 shrink-0 bg-base-200"
                    >
                        <img :src="state.previewUrl" alt="Vista previa de foto" class="size-full object-cover" />
                    </div>
                    <div
                        v-else
                        class="size-10 sm:size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0"
                    >
                        <FileText class="size-5 sm:size-6" />
                    </div>

                    <div class="min-w-0 flex-1 flex flex-col">
                        <p class="text-xs sm:text-sm font-bold text-base-content truncate break-all block" :title="state.fileName">
                            {{ state.fileName }}
                        </p>
                        <p class="text-[11px] sm:text-xs text-base-content/60 font-mono mt-0.5">
                            {{ formatBytes(state.fileSize) }}
                        </p>
                    </div>
                </div>

                <!-- Action Buttons: Replace / Delete -->
                <div class="flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        class="btn btn-soft btn-xs gap-1 font-semibold"
                        title="Reemplazar archivo"
                        @click="openFileDialog"
                    >
                        <RotateCw class="size-3.5" />
                        <span class="hidden sm:inline">Cambiar</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs btn-square text-error"
                        title="Quitar archivo"
                        @click="emit('remove')"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <!-- AI Status Badges & Info -->
            <div class="mt-3 pt-3 border-t border-base-200 flex flex-wrap items-center justify-between gap-2 w-full min-w-0" role="status" aria-live="polite">
                <!-- Success -->
                <div v-if="state.aiStatus === 'success'" class="badge badge-success badge-sm gap-1 font-medium max-w-full">
                    <CheckCircle2 class="size-3.5 shrink-0" />
                    <span class="truncate">Datos leídos por IA</span>
                </div>

                <!-- Warning (e.g. vigencia/fecha) -->
                <div v-else-if="state.aiStatus === 'warning'" class="badge badge-warning badge-sm gap-1 font-medium max-w-full">
                    <AlertTriangle class="size-3.5 shrink-0" />
                    <span class="truncate">Revisión requerida</span>
                </div>

                <!-- Degradado / Falló IA -->
                <div v-else-if="state.aiStatus === 'failed'" class="badge badge-info badge-sm gap-1 font-medium max-w-full">
                    <CheckCircle2 class="size-3.5 shrink-0" />
                    <span class="truncate">Documento listo • Revisión manual</span>
                </div>

                <!-- Photo (not applicable) -->
                <div v-else-if="state.aiStatus === 'not_applicable'" class="badge badge-primary badge-sm gap-1 font-medium max-w-full">
                    <CheckCircle2 class="size-3.5 shrink-0" />
                    <span class="truncate">Fotografía lista</span>
                </div>
            </div>

            <!-- Warnings alerts if any -->
            <div v-if="state.warnings && state.warnings.length > 0" class="mt-3 space-y-2 w-full min-w-0">
                <div
                    v-for="(warning, idx) in state.warnings"
                    :key="idx"
                    role="alert"
                    class="alert alert-soft text-xs py-2.5 px-3 rounded-xl flex items-start gap-2 shadow-xs w-full min-w-0"
                    :class="state.aiStatus === 'failed' ? 'alert-info' : 'alert-warning'"
                >
                    <AlertTriangle v-if="state.aiStatus !== 'failed'" class="size-4 shrink-0 mt-0.5" />
                    <CheckCircle2 v-else class="size-4 shrink-0 mt-0.5" />
                    <div class="leading-relaxed flex-1 min-w-0">
                        <span class="block break-words font-medium">{{ warning }}</span>
                        <p v-if="state.aiStatus === 'failed'" class="mt-1 opacity-80 break-words">
                            Tu archivo ya está guardado correctamente. Podrás ingresar tus datos manualmente en el siguiente paso.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty / Error / Ready to Upload State -->
        <div
            v-else
            class="relative border-2 border-dashed rounded-2xl p-6 transition-all duration-200 text-center flex flex-col items-center justify-center cursor-pointer select-none outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 w-full min-w-0"
            :class="[
                isDragging
                    ? 'border-primary bg-primary/10 scale-[1.01]'
                    : (state.error || errorMsg)
                        ? 'border-error/60 bg-error/5 hover:border-error'
                        : 'border-base-300 bg-base-100 hover:border-primary/50 hover:bg-base-200/40'
            ]"
            tabindex="0"
            role="button"
            :aria-label="`Subir ${label}`"
            @click="openFileDialog"
            @keydown.enter.prevent="openFileDialog"
            @keydown.space.prevent="openFileDialog"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
        >
            <div
                class="size-12 rounded-full flex items-center justify-center mb-3 shrink-0"
                :class="(state.error || errorMsg) ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary'"
            >
                <AlertTriangle v-if="state.error || errorMsg" class="size-6" />
                <ImageIcon v-else-if="kind === 'photo'" class="size-6" />
                <UploadCloud v-else class="size-6" />
            </div>

            <p class="text-sm font-semibold text-base-content max-w-full">
                <span v-if="state.error || errorMsg" class="text-error font-bold">Error: Clic para elegir otro archivo</span>
                <span v-else><span class="text-primary underline font-bold">Selecciona un archivo</span> o arrástralo aquí</span>
            </p>

            <p v-if="state.error || errorMsg" class="text-xs text-error font-medium mt-1 max-w-sm break-words">
                {{ state.error || errorMsg }}
            </p>
            <p v-else class="text-xs text-base-content/60 mt-1 max-w-sm">
                {{ helperText }}
            </p>
        </div>

        <!-- Field Validation Error -->
        <span v-if="state.error || errorMsg" class="text-error text-xs mt-1.5 font-semibold flex items-center gap-1" role="alert">
            <AlertTriangle class="size-3.5 shrink-0" />
            <span class="break-words">{{ state.error || errorMsg }}</span>
        </span>
    </div>
</template>
