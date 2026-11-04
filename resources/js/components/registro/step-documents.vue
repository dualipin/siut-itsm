<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRight, ShieldCheck, Sparkles } from '@lucide/vue'
import DocumentDropzone from './document-dropzone.vue'
import type { UploadedDocState } from './document-dropzone.vue'

const props = defineProps<{
    ineState: UploadedDocState
    nominaState: UploadedDocState
    photoState: UploadedDocState
    errors: Record<string, string[] | undefined>
    isUploadingAny: boolean
}>()

const emit = defineEmits<{
    (e: 'upload-file', kind: 'ine' | 'nomina' | 'photo', file: File): void
    (e: 'remove-file', kind: 'ine' | 'nomina' | 'photo'): void
    (e: 'cancel-upload', kind: 'ine' | 'nomina' | 'photo'): void
    (e: 'next'): void
}>()

const canProceed = computed(() => {
    return (
        Boolean(props.ineState.token) &&
        Boolean(props.nominaState.token) &&
        Boolean(props.photoState.token) &&
        !props.isUploadingAny
    )
})
</script>

<template>
    <section aria-labelledby="step-1-title" class="space-y-6">
        <div>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary mb-2">
                <Sparkles class="size-3.5" />
                Paso 1 de 3
            </div>
            <h2 id="step-1-title" class="text-2xl sm:text-3xl font-display font-black text-base-content tracking-tight">
                Documentos de Identificación
            </h2>
            <p class="text-sm text-base-content/70 mt-1 max-w-xl">
                Sube tu credencial INE (frente), tu comprobante de pago reciente y tu fotografía oficial. Nuestra inteligencia artificial extraerá tus datos automáticamente para que no tengas que capturarlos a mano.
            </p>
        </div>

        <div class="space-y-6">
            <!-- INE -->
            <DocumentDropzone
                kind="ine"
                label="1. Credencial INE (Frente)"
                helperText="Archivo PDF, JPG, PNG o WebP. Máx. 10 MB. Se extraerá tu nombre, apellidos y CURP."
                :max-size-mb="10"
                :state="ineState"
                :error-msg="errors.ine_token?.[0]"
                @file-selected="(file) => emit('upload-file', 'ine', file)"
                @remove="emit('remove-file', 'ine')"
                @cancel="emit('cancel-upload', 'ine')"
            />

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
                helperText="Obligatoria para tu credencial digital. Sube una foto de frente con fondo claro o tómala con tu cámara."
                accept="image/jpeg,image/png,image/webp"
                capture="user"
                :max-size-mb="2"
                :state="photoState"
                :error-msg="errors.photo_token?.[0]"
                @file-selected="(file) => emit('upload-file', 'photo', file)"
                @remove="emit('remove-file', 'photo')"
                @cancel="emit('cancel-upload', 'photo')"
            />
        </div>

        <!-- Mensaje de garantía IA no bloqueante -->
        <div class="alert alert-soft bg-base-100 border border-base-300 rounded-2xl p-4 flex items-start gap-3">
            <ShieldCheck class="size-5 text-primary shrink-0 mt-0.5" />
            <div class="text-xs text-base-content/70">
                <strong class="font-semibold text-base-content">Tu avance está garantizado:</strong> La verificación por IA agiliza el llenado pero nunca te bloquea. Si un dato no se detecta con total certeza, podrás revisarlo y capturarlo manualmente en el siguiente paso.
            </div>
        </div>

        <!-- Botón continuar -->
        <div class="pt-4 flex justify-end">
            <button
                type="button"
                class="btn btn-primary btn-md sm:btn-lg gap-2 font-semibold shadow-md cursor-pointer"
                :disabled="!canProceed"
                @click="emit('next')"
            >
                <span v-if="isUploadingAny">Subiendo documentos...</span>
                <span v-else>Continuar a tus datos</span>
                <ArrowRight class="size-5" />
            </button>
        </div>
    </section>
</template>
