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
        emit('file-selected', file)
    }
    // Reset so the same file could be selected again if replaced
    target.value = ''
}

function handleDrop(event: DragEvent) {
    isDragging.value = false
    const file = event.dataTransfer?.files?.[0]
    if (file) {
        emit('file-selected', file)
    }
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
    <div class="fieldset w-full">
        <label class="fieldset-label font-bold text-base text-base-content flex items-center justify-between">
            <span>{{ label }}</span>
            <span class="text-xs font-normal text-base-content/60">Máx. {{ maxSizeMb }} MB</span>
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

        <!-- Empty / Upload State (No file uploaded yet) -->
        <div
            v-if="!state.file && !state.token"
            class="relative border-2 border-dashed rounded-2xl p-6 transition-all duration-200 text-center flex flex-col items-center justify-center cursor-pointer select-none outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            :class="[
                isDragging
                    ? 'border-primary bg-primary/10 scale-[1.01]'
                    : errorMsg
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
            <div class="size-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-3">
                <ImageIcon v-if="kind === 'photo'" class="size-6" />
                <UploadCloud v-else class="size-6" />
            </div>

            <p class="text-sm font-semibold text-base-content">
                <span class="text-primary underline font-bold">Selecciona un archivo</span> o arrástralo aquí
            </p>

            <p class="text-xs text-base-content/60 mt-1 max-w-sm">
                {{ helperText }}
            </p>
        </div>

        <!-- In-Progress Upload State -->
        <div
            v-else-if="state.uploading"
            class="border border-base-300 bg-base-100 rounded-2xl p-5 shadow-xs"
        >
            <div class="flex items-center justify-between gap-3 mb-2">
                <div class="flex items-center gap-2.5 min-w-0">
                    <Loader2 class="size-5 text-primary animate-spin shrink-0" />
                    <span class="text-sm font-medium text-base-content truncate">
                        Subiendo {{ state.fileName || 'archivo' }}...
                    </span>
                </div>
                <span class="text-xs font-mono font-bold text-primary">{{ state.progress }}%</span>
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

        <!-- Uploaded / Processed State -->
        <div
            v-else
            class="border border-base-300 bg-base-100 rounded-2xl p-4 sm:p-5 shadow-sm transition-all"
        >
            <div class="flex items-start justify-between gap-3">
                <!-- Preview / Icon + File Details -->
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Photo thumbnail or Document Icon -->
                    <div
                        v-if="kind === 'photo' && state.previewUrl"
                        class="size-14 rounded-xl overflow-hidden border border-base-300 shrink-0 bg-base-200"
                    >
                        <img :src="state.previewUrl" alt="Vista previa de foto" class="size-full object-cover" />
                    </div>
                    <div
                        v-else
                        class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0"
                    >
                        <FileText class="size-6" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-bold text-base-content truncate max-w-xs">
                            {{ state.fileName }}
                        </p>
                        <p class="text-xs text-base-content/60 font-mono mt-0.5">
                            {{ formatBytes(state.fileSize) }}
                        </p>
                    </div>
                </div>

                <!-- Action Buttons: Replace / Delete -->
                <div class="flex items-center gap-1.5 shrink-0">
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
            <div class="mt-3 pt-3 border-t border-base-200 flex flex-wrap items-center justify-between gap-2" role="status" aria-live="polite">
                <!-- Success -->
                <div v-if="state.aiStatus === 'success'" class="badge badge-success badge-sm gap-1 font-medium">
                    <CheckCircle2 class="size-3.5" />
                    Datos leídos por IA
                </div>

                <!-- Warning (e.g. vigencia/fecha) -->
                <div v-else-if="state.aiStatus === 'warning'" class="badge badge-warning badge-sm gap-1 font-medium">
                    <AlertTriangle class="size-3.5" />
                    Revisión requerida
                </div>

                <!-- Degradado / Falló IA -->
                <div v-else-if="state.aiStatus === 'failed'" class="badge badge-info badge-sm gap-1 font-medium">
                    Revisión manual
                </div>

                <!-- Photo (not applicable) -->
                <div v-else-if="state.aiStatus === 'not_applicable'" class="badge badge-primary badge-sm gap-1 font-medium">
                    <CheckCircle2 class="size-3.5" />
                    Fotografía lista
                </div>
            </div>

            <!-- Warnings alerts if any -->
            <div v-if="state.warnings && state.warnings.length > 0" class="mt-3 space-y-1.5">
                <div
                    v-for="(warning, idx) in state.warnings"
                    :key="idx"
                    class="alert alert-warning text-xs py-2 px-3 rounded-xl flex items-start gap-2 shadow-xs"
                >
                    <AlertTriangle class="size-4 shrink-0 text-warning mt-0.5" />
                    <span class="leading-relaxed">{{ warning }}</span>
                </div>
            </div>
        </div>

        <!-- Field Validation Error -->
        <span v-if="errorMsg" class="fieldset-label text-error text-xs mt-1.5 font-semibold flex items-center gap-1" role="alert">
            <AlertTriangle class="size-3.5 shrink-0" />
            {{ errorMsg }}
        </span>
    </div>
</template>
