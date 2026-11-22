<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight, CheckCircle2, CreditCard, ShieldCheck, Sparkles } from '@lucide/vue'
import DocumentDropzone from './document-dropzone.vue'
import type { DocumentKind, UploadedDocState } from './document-dropzone.vue'

const props = defineProps<{
    ineState: UploadedDocState
    ineReversoState: UploadedDocState
    nominaState: UploadedDocState
    photoState: UploadedDocState
    errors: Record<string, string[] | undefined>
    isUploadingAny: boolean
}>()

const emit = defineEmits<{
    (e: 'upload-file', kind: DocumentKind, file: File): void
    (e: 'remove-file', kind: DocumentKind): void
    (e: 'cancel-upload', kind: DocumentKind): void
    (e: 'next'): void
}>()

const missingDocuments = computed(() => {
    const list: string[] = []
    if (!props.ineState.token) list.push('INE (parte frontal)')
    if (!props.ineReversoState.token) list.push('INE (parte posterior)')
    if (!props.nominaState.token) list.push('Comprobante de nómina')
    if (!props.photoState.token) list.push('Fotografía de perfil')
    return list
})

const canProceed = computed(() => {
    return (
        Boolean(props.ineState.token) &&
        Boolean(props.ineReversoState.token) &&
        Boolean(props.nominaState.token) &&
        Boolean(props.photoState.token) &&
        !props.isUploadingAny
    )
})
</script>

<template>
    <section aria-labelledby="step-1-title" class="space-y-6 scroll-mt-44 min-w-0 w-full">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary mb-2">
                <Sparkles class="size-3.5" />
                Paso 1 de 3
            </div>
            <h2 id="step-1-title" class="text-2xl sm:text-3xl font-display font-black text-base-content tracking-tight">
                Documentos de Identificación
            </h2>
            <p class="text-sm text-base-content/70 mt-1 max-w-xl">
                Sube ambos lados de tu credencial INE (frontal y posterior), tu comprobante de pago reciente y tu fotografía oficial. Validaremos automáticamente que tu INE sea auténtica, vigente y corresponda al mismo titular.
            </p>
        </div>

        <div class="space-y-6 min-w-0 w-full">
            <!-- Grupo INE: Parte Frontal y Parte Posterior -->
            <div class="rounded-2xl border border-base-300 bg-base-200/30 p-4 sm:p-5 space-y-5 min-w-0 w-full">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="size-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <CreditCard class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-bold text-sm sm:text-base text-base-content">
                                1. Credencial INE (Ambos lados obligatorios)
                            </h3>
                            <p class="text-xs text-base-content/60">
                                Sube por separado el frente y el reverso en imágenes claras y sin reflejos.
                            </p>
                        </div>
                    </div>
                    <span
                        v-if="ineState.token && ineReversoState.token"
                        class="badge badge-success badge-soft badge-sm gap-1 font-semibold shrink-0"
                    >
                        <CheckCircle2 class="size-3.5" />
                        INE completa
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-5 min-w-0 w-full">
                    <!-- INE Frente -->
                    <DocumentDropzone
                        kind="ine"
                        label="Parte Frontal (Anverso)"
                        helperText="Lado con fotografía, nombre completo, CURP y vigencia. PDF, JPG, PNG o WebP."
                        :max-size-mb="10"
                        :state="ineState"
                        :error-msg="errors.ine_token?.[0]"
                        @file-selected="(file) => emit('upload-file', 'ine', file)"
                        @remove="emit('remove-file', 'ine')"
                        @cancel="emit('cancel-upload', 'ine')"
                    />

                    <!-- INE Reverso -->
                    <DocumentDropzone
                        kind="ine_reverso"
                        label="Parte Posterior (Reverso)"
                        helperText="Lado con códigos QR, firma/huella y zona de lectura (IDMEX). PDF, JPG, PNG o WebP."
                        :max-size-mb="10"
                        :state="ineReversoState"
                        :error-msg="errors.ine_reverso_token?.[0]"
                        @file-selected="(file) => emit('upload-file', 'ine_reverso', file)"
                        @remove="emit('remove-file', 'ine_reverso')"
                        @cancel="emit('cancel-upload', 'ine_reverso')"
                    />
                </div>
            </div>

            <!-- Nómina -->
            <DocumentDropzone
                kind="nomina"
                label="2. Comprobante de Pago (Nómina reciente)"
                helperText="Archivo PDF, JPG, PNG o WebP. Máx. 10 MB. Se extraerá tu NSS, categoría y salario."
                :max-size-mb="10"
                :state="nominaState"
                :error-msg="errors.nomina_token?.[0]"
                @file-selected="(file) => emit('upload-file', 'nomina', file)"
                @remove="emit('remove-file', 'nomina')"
                @cancel="emit('cancel-upload', 'nomina')"
            />

            <!-- Fotografía -->
            <DocumentDropzone
                kind="photo"
                label="3. Fotografía de Perfil"
                helperText="Obligatoria para tu credencial digital. Sube una foto de frente con fondo claro o tómala con tu cámara. Máx. 10 MB."
                accept="image/jpeg,image/png,image/webp"
                capture="user"
                :max-size-mb="10"
                :state="photoState"
                :error-msg="errors.photo_token?.[0]"
                @file-selected="(file) => emit('upload-file', 'photo', file)"
                @remove="emit('remove-file', 'photo')"
                @cancel="emit('cancel-upload', 'photo')"
            />
        </div>

        <!-- Mensaje de verificación -->
        <div class="alert alert-soft bg-base-100 border border-base-300 rounded-2xl p-4 flex items-start gap-3">
            <ShieldCheck class="size-5 text-primary shrink-0 mt-0.5" />
            <div class="text-xs text-base-content/70">
                <strong class="font-semibold text-base-content">Validación automática de INE:</strong> Verificamos que tanto la parte frontal como la posterior sean legibles, vigentes y pertenezcan a la misma credencial. Si algún dato personal requiere ajuste, podrás corregirlo en el siguiente paso.
            </div>
        </div>

        <!-- Botón continuar -->
        <div class="pt-4 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
            <div v-if="missingDocuments.length > 0" class="text-xs text-base-content/80 bg-base-200/60 px-4 py-2.5 rounded-xl border border-base-300/60">
                <span class="font-semibold text-warning">Falta subir para continuar:</span>
                <span class="ml-1 font-medium">{{ missingDocuments.join(' • ') }}</span>
            </div>
            <div v-else class="text-xs text-success flex items-center gap-1.5 font-semibold">
                <CheckCircle2 class="size-4" />
                <span>4 de 4 archivos listos</span>
            </div>

            <button
                type="button"
                class="btn btn-primary btn-md sm:btn-lg gap-2 font-semibold shadow-md cursor-pointer shrink-0"
                :disabled="!canProceed"
                @click="emit('next')"
            >
                <span v-if="isUploadingAny">Validando documentos...</span>
                <span v-else>Continuar a tus datos</span>
                <ArrowRight class="size-5" />
            </button>
        </div>
    </section>
</template>
