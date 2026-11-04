<script setup lang="ts">
import { computed, ref } from 'vue'
import { User, Sparkles, ChevronDown, ChevronUp } from '@lucide/vue'

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
    organization?: string
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
    organization: 'Sindicato Único de Trabajadores del ITSM',
    website: 'www.siutitsm.com',
})

const isExpandedMobile = ref(false)

const displayName = computed(() => {
    const full = `${props.name || ''} ${props.surnames || ''}`.trim()
    return full || 'Tu Nombre Completo'
})

const displayCurp = computed(() => {
    return (props.curp || '').toUpperCase().trim() || 'CURP — 18 DÍGITOS'
})

const displayNss = computed(() => {
    return (props.nss || '').trim() || 'NSS — 11 DÍGITOS'
})

const displayCategory = computed(() => {
    return (props.category || '').trim() || 'Puesto o categoría laboral'
})
</script>

<template>
    <aside aria-label="Credencial de agremiado digital en vivo" class="w-full">
        <!-- Barra compacta en móvil (< lg) con opción de expandir -->
        <div class="lg:hidden mb-6 bg-base-100 rounded-box border border-base-300 shadow-sm overflow-hidden">
            <button
                type="button"
                @click="isExpandedMobile = !isExpandedMobile"
                class="w-full p-4 flex items-center justify-between text-left transition-colors hover:bg-base-200/50"
                :aria-expanded="isExpandedMobile"
                aria-controls="mobile-credential-body"
            >
                <div class="flex items-center gap-3 min-w-0">
                    <div class="relative size-10 rounded-full overflow-hidden bg-base-200 border border-primary/30 shrink-0">
                        <img
                            v-if="photoUrl"
                            :src="photoUrl"
                            alt="Fotografía"
                            class="size-full object-cover"
                        />
                        <div v-else class="size-full flex items-center justify-center text-base-content/40">
                            <User class="size-5" />
                        </div>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-semibold uppercase tracking-wider text-primary">Credencial en Vivo</span>
                            <span v-if="isScanning" class="badge badge-primary badge-xs animate-pulse">Escaneando...</span>
                        </div>
                        <p class="text-sm font-bold truncate text-base-content">{{ displayName }}</p>
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
                class="p-4 pt-0 border-t border-base-200"
            >
                <div class="mt-4 flex justify-center">
                    <!-- Tarjeta de la credencial en móvil -->
                    <div class="relative w-full max-w-[280px] aspect-[54/85.6] bg-base-100 rounded-2xl shadow-xl overflow-hidden border border-base-300 text-center select-none flex flex-col justify-between">
                        <!-- Arcos superior e inferior -->
                        <div
                            class="absolute inset-x-0 top-0 h-[38%] bg-cover bg-top bg-no-repeat pointer-events-none z-1"
                            :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
                        />
                        <div
                            class="absolute inset-x-0 bottom-0 h-[38%] bg-cover bg-bottom bg-no-repeat pointer-events-none z-2 scale-y-[-1] scale-x-[-1]"
                            :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
                        />

                        <!-- Marca de agua central -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-10 pointer-events-none z-3">
                            <img :src="logoSiutUrl" alt="Marca de agua SIUT" class="size-24 rounded-full object-contain" />
                        </div>

                        <!-- Efecto de escaneo IA -->
                        <div
                            v-if="isScanning"
                            class="absolute inset-0 pointer-events-none z-20 motion-safe:animate-pulse bg-gradient-to-b from-primary/10 via-primary/5 to-transparent"
                            aria-hidden="true"
                        >
                            <div class="w-full h-1 bg-primary/70 shadow-[0_0_12px_var(--siut-color-primary)] animate-bounce" />
                        </div>

                        <!-- Contenido Principal -->
                        <div class="relative z-10 size-full flex flex-col justify-between p-3.5">
                            <div class="pt-2 flex flex-col items-center">
                                <!-- Foto circular -->
                                <div class="size-20 rounded-full border-[3px] border-primary bg-base-100 shadow-sm overflow-hidden flex items-center justify-center shrink-0">
                                    <img
                                        v-if="photoUrl"
                                        :src="photoUrl"
                                        alt="Foto"
                                        class="size-full object-cover"
                                    />
                                    <div v-else class="flex flex-col items-center justify-center text-base-content/40 text-[9px] font-medium leading-none">
                                        <User class="size-6 mb-1 text-base-content/30" />
                                        <span>SIN FOTO</span>
                                    </div>
                                </div>

                                <!-- Datos del agremiado -->
                                <div class="mt-2 w-full px-1">
                                    <div class="font-bold text-xs uppercase tracking-tight text-primary px-1.5 py-0.5 rounded bg-base-100/90 shadow-2xs inline-block max-w-full truncate leading-tight">
                                        {{ displayName }}
                                    </div>
                                    <div class="text-[9px] text-base-content/60 uppercase font-semibold mt-0.5 truncate">
                                        {{ displayCategory }}
                                    </div>
                                    <div class="text-[7.5px] font-bold uppercase text-base-content/80 leading-tight mt-1 line-clamp-1">
                                        {{ organization }}
                                    </div>
                                    <div class="text-[7.5px] font-semibold text-base-content/60">
                                        REG. RS-033/2019.
                                    </div>
                                    <div class="text-[8.5px] font-bold text-base-content/80 mt-1">
                                        IMSS: <span class="font-mono">{{ displayNss }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer con logos y web -->
                            <div class="pb-1 flex flex-col items-center">
                                <div class="flex items-center justify-center gap-3 w-full">
                                    <img :src="logoTecnmUrl" alt="TecNM" class="h-4 max-w-[28px] object-contain rounded-full" />
                                    <img :src="logoItsmUrl" alt="ITSM" class="h-4 max-w-[28px] object-cover rounded-full" />
                                    <img :src="logoSiutUrl" alt="SIUT" class="h-4 max-w-[28px] object-cover rounded-full" />
                                </div>
                                <div class="text-[8px] font-bold text-primary mt-1">
                                    {{ website }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjeta de Credencial fija (Sticky en desktop lg+) -->
        <div class="hidden lg:block sticky top-24">
            <div class="text-center mb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-primary inline-flex items-center gap-1.5">
                    <Sparkles class="size-3.5" />
                    Credencial Digital en Vivo
                </span>
                <p class="text-xs text-base-content/60 mt-0.5">Se actualiza automáticamente con tus documentos</p>
            </div>

            <!-- Contenedor PVC réplica fiel al anverso del PDF -->
            <div class="relative w-full max-w-[280px] mx-auto aspect-[54/85.6] bg-base-100 rounded-2xl shadow-xl overflow-hidden border border-base-300 text-center select-none flex flex-col justify-between">
                <!-- Arcos superior e inferior -->
                <div
                    class="absolute inset-x-0 top-0 h-[38%] bg-cover bg-top bg-no-repeat pointer-events-none z-1"
                    :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
                />
                <div
                    class="absolute inset-x-0 bottom-0 h-[38%] bg-cover bg-bottom bg-no-repeat pointer-events-none z-2 scale-y-[-1] scale-x-[-1]"
                    :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
                />

                <!-- Marca de agua central -->
                <div class="absolute inset-0 flex items-center justify-center opacity-10 pointer-events-none z-3">
                    <img :src="logoSiutUrl" alt="Marca de agua SIUT" class="size-24 rounded-full object-contain" />
                </div>

                <!-- Efecto de escaneo IA -->
                <div
                    v-if="isScanning"
                    class="absolute inset-0 pointer-events-none z-20 motion-safe:animate-pulse bg-gradient-to-b from-primary/10 via-primary/5 to-transparent"
                    aria-hidden="true"
                >
                    <div class="w-full h-1 bg-primary/70 shadow-[0_0_12px_var(--siut-color-primary)] animate-bounce" />
                </div>

                <!-- Contenido Principal -->
                <div class="relative z-10 size-full flex flex-col justify-between p-3.5">
                    <div class="pt-2 flex flex-col items-center">
                        <!-- Foto circular -->
                        <div class="size-20 rounded-full border-[3px] border-primary bg-base-100 shadow-sm overflow-hidden flex items-center justify-center shrink-0">
                            <img
                                v-if="photoUrl"
                                :src="photoUrl"
                                alt="Foto"
                                class="size-full object-cover"
                            />
                            <div v-else class="flex flex-col items-center justify-center text-base-content/40 text-[9px] font-medium leading-none">
                                <User class="size-6 mb-1 text-base-content/30" />
                                <span>SIN FOTO</span>
                            </div>
                        </div>

                        <!-- Datos del agremiado -->
                        <div class="mt-2 w-full px-1">
                            <div class="font-bold text-xs uppercase tracking-tight text-primary px-1.5 py-0.5 rounded bg-base-100/90 shadow-2xs inline-block max-w-full truncate leading-tight">
                                {{ displayName }}
                            </div>
                            <div class="text-[9px] text-base-content/60 uppercase font-semibold mt-0.5 truncate">
                                {{ displayCategory }}
                            </div>
                            <div class="text-[7.5px] font-bold uppercase text-base-content/80 leading-tight mt-1 line-clamp-1">
                                {{ organization }}
                            </div>
                            <div class="text-[7.5px] font-semibold text-base-content/60">
                                REG. RS-033/2019.
                            </div>
                            <div class="text-[8.5px] font-bold text-base-content/80 mt-1">
                                IMSS: <span class="font-mono">{{ displayNss }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer con logos y web -->
                    <div class="pb-1 flex flex-col items-center">
                        <div class="flex items-center justify-center gap-3 w-full">
                            <img :src="logoTecnmUrl" alt="TecNM" class="h-4 max-w-[28px] object-contain rounded-full" />
                            <img :src="logoItsmUrl" alt="ITSM" class="h-4 max-w-[28px] object-cover rounded-full" />
                            <img :src="logoSiutUrl" alt="SIUT" class="h-4 max-w-[28px] object-cover rounded-full" />
                        </div>
                        <div class="text-[8px] font-bold text-primary mt-1">
                            {{ website }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
