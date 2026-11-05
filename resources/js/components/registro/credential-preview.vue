<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ChevronDown, ChevronUp, Sparkles, User } from '@lucide/vue'
import QRCodeStyling from 'qr-code-styling'
import CredentialCard from './credential-card.vue'

interface Props {
    name?: string
    surnames?: string
    curp?: string
    nss?: string
    category?: string
    photoUrl?: string | null
    isScanning?: boolean
    backgroundImageUrl?: string
    logoTecnmUrl?: string
    logoItsmUrl?: string
    logoSiutUrl?: string
    website?: string
}

const props = withDefaults(defineProps<Props>(), {
    name: '',
    surnames: '',
    curp: '',
    nss: '',
    category: '',
    photoUrl: null,
    isScanning: false,
    backgroundImageUrl: '/assets/images/background-id.png',
    logoTecnmUrl: '/assets/img/logo/logo-tecnm.png',
    logoItsmUrl: '/assets/img/logo/logo-itsm.jpg',
    logoSiutUrl: '/assets/images/logo.webp',
    website: 'www.siutitsm.com.mx',
})

const isExpandedMobile = ref(false)

const displayName = computed(() => {
    const full = `${props.name || ''} ${props.surnames || ''}`.trim()
    return full || 'Tu Nombre Completo'
})

const displayNss = computed(() => {
    return (props.nss || '').trim() || 'NSS — 11 DÍGITOS'
})

const displayCategory = computed(() => {
    return (props.category || '').trim() || 'Puesto o categoría laboral'
})

const qrSrc = ref<string | null>(null)
let qrObjectUrl: string | null = null

// ponytail: QR estático al sitio; cambiar a token cuando exista /verificar/{token}
const qrTargetUrl = computed(() => {
    const website = props.website.replace(/^https?:\/\//, '')
    return `https://${website}`
})

async function generateQr(): Promise<void> {
    try {
        const qr = new QRCodeStyling({
            width: 160,
            height: 160,
            type: 'png',
            data: qrTargetUrl.value,
            image: props.logoSiutUrl,
            qrOptions: {
                errorCorrectionLevel: 'H',
            },
            imageOptions: {
                crossOrigin: 'anonymous',
                margin: 1,
                imageSize: 0.22,
            },
            dotsOptions: {
                color: '#2E3A6B',
                type: 'square',
            },
            cornersSquareOptions: {
                color: '#2E3A6B',
                type: 'square',
            },
            cornersDotOptions: {
                color: '#2E3A6B',
                type: 'square',
            },
            backgroundOptions: {
                color: '#ffffff',
            },
        })

        const raw = await qr.getRawData('png')

        if (!raw) {
            return
        }

        const blob = raw instanceof Blob ? raw : new Blob([raw], { type: 'image/png' })

        if (qrObjectUrl) {
            URL.revokeObjectURL(qrObjectUrl)
        }

        qrObjectUrl = URL.createObjectURL(blob)
        qrSrc.value = qrObjectUrl
    } catch {
        qrSrc.value = null
    }
}

onMounted(generateQr)

watch(() => [props.website, props.logoSiutUrl], generateQr)

onBeforeUnmount(() => {
    if (qrObjectUrl) {
        URL.revokeObjectURL(qrObjectUrl)
    }
})
</script>

<template>
    <aside aria-label="Credencial de agremiado digital en vivo" class="w-full">
        <!-- Barra compacta en móvil (< lg) con opción de expandir -->
        <div class="mb-6 overflow-hidden rounded-box border border-base-300 bg-base-100 shadow-sm lg:hidden">
            <button
                type="button"
                class="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-base-200/50"
                :aria-expanded="isExpandedMobile"
                aria-controls="mobile-credential-body"
                @click="isExpandedMobile = !isExpandedMobile"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div class="relative size-10 shrink-0 overflow-hidden rounded-full border border-primary/30 bg-base-200">
                        <img
                            v-if="photoUrl"
                            :src="photoUrl"
                            alt="Fotografía"
                            class="size-full object-cover"
                        />
                        <div v-else class="flex size-full items-center justify-center text-base-content/40">
                            <User class="size-5" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary">Credencial en Vivo</span>
                            <span v-if="isScanning" class="badge badge-primary badge-xs animate-pulse">Escaneando...</span>
                        </div>
                        <p class="truncate text-sm font-bold text-base-content">{{ displayName }}</p>
                    </div>
                </div>
                <div class="shrink-0 text-base-content/60">
                    <ChevronUp v-if="isExpandedMobile" class="size-5" />
                    <ChevronDown v-else class="size-5" />
                </div>
            </button>

            <!-- Desplegable en móvil -->
            <div
                v-show="isExpandedMobile"
                id="mobile-credential-body"
                class="border-t border-base-200 p-4 pt-0"
            >
                <div class="mt-4 flex justify-center">
                    <CredentialCard
                        :display-name="displayName"
                        :display-nss="displayNss"
                        :display-category="displayCategory"
                        :photo-url="photoUrl"
                        :qr-src="qrSrc"
                        :is-scanning="isScanning"
                        :background-image-url="backgroundImageUrl"
                        :logo-tecnm-url="logoTecnmUrl"
                        :logo-itsm-url="logoItsmUrl"
                        :logo-siut-url="logoSiutUrl"
                        :website="website"
                    />
                </div>
            </div>
        </div>

        <!-- Tarjeta de Credencial fija (Sticky en desktop lg+) -->
        <div class="sticky top-24 hidden lg:block">
            <div class="mb-3 text-center">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary">
                    <Sparkles class="size-3.5" />
                    Credencial Digital en Vivo
                </span>
                <p class="mt-0.5 text-xs text-base-content/60">Se actualiza automáticamente con tus documentos</p>
            </div>

            <CredentialCard
                :display-name="displayName"
                :display-nss="displayNss"
                :display-category="displayCategory"
                :photo-url="photoUrl"
                :qr-src="qrSrc"
                :is-scanning="isScanning"
                :background-image-url="backgroundImageUrl"
                :logo-tecnm-url="logoTecnmUrl"
                :logo-itsm-url="logoItsmUrl"
                :logo-siut-url="logoSiutUrl"
                :website="website"
                class="mx-auto"
            />
        </div>
    </aside>
</template>
