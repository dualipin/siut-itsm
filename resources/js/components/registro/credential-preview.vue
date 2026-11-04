<script setup lang="ts">
import { computed, ref } from 'vue'
import { User, ShieldCheck, Sparkles, ChevronDown, ChevronUp } from '@lucide/vue'

const props = defineProps<{
    name?: string
    surnames?: string
    curp?: string
    nss?: string
    category?: string
    photoUrl?: string | null
    isScanning?: boolean
}>()

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
                    <div class="relative size-10 rounded-full overflow-hidden bg-base-200 border border-primary/20 shrink-0">
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
                <!-- Render de la credencial completa en móvil -->
                <div class="mt-4">
                    <!-- Tarjeta de la credencial -->
                    <div
                        class="relative rounded-2xl bg-gradient-to-br from-base-100 via-base-100 to-base-200 border-2 border-primary/30 p-5 shadow-xl overflow-hidden"
                    >
                        <!-- Efecto de escaneo IA -->
                        <div
                            v-if="isScanning"
                            class="absolute inset-0 pointer-events-none z-20 motion-safe:animate-pulse bg-gradient-to-b from-primary/10 via-primary/5 to-transparent"
                            aria-hidden="true"
                        >
                            <div class="w-full h-1 bg-primary/60 shadow-[0_0_12px_var(--siut-color-primary)] animate-bounce" />
                        </div>

                        <!-- Encabezado de la credencial -->
                        <div class="flex items-start justify-between gap-3 pb-3 border-b border-base-300">
                            <div>
                                <span class="text-[10px] font-black tracking-widest text-primary uppercase block">SIUT ITSM</span>
                                <span class="text-[11px] font-bold text-base-content/80 leading-tight block">Sindicato Independiente de Trabajadores</span>
                            </div>
                            <span class="badge badge-primary badge-sm gap-1 font-mono text-[10px] uppercase font-bold shrink-0">
                                <ShieldCheck class="size-3" />
                                Aspirante
                            </span>
                        </div>

                        <!-- Contenido central: Foto y datos -->
                        <div class="mt-4 flex gap-4 items-center">
                            <div class="relative size-20 rounded-xl overflow-hidden bg-base-200 border-2 border-primary/40 shrink-0 shadow-inner flex items-center justify-center">
                                <img
                                    v-if="photoUrl"
                                    :src="photoUrl"
                                    alt="Fotografía del agremiado"
                                    class="size-full object-cover transition-transform duration-300"
                                />
                                <User v-else class="size-10 text-base-content/30" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <span class="text-[10px] font-medium text-base-content/60 uppercase tracking-wider block">Agremiado(a)</span>
                                <h3 class="font-serif text-base font-bold text-base-content leading-snug line-clamp-2">
                                    {{ displayName }}
                                </h3>
                                <p class="text-xs text-primary font-medium truncate">{{ displayCategory }}</p>
                            </div>
                        </div>

                        <!-- Datos clave -->
                        <div class="mt-4 pt-3 border-t border-base-200 grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-base-200/60 rounded-lg p-2">
                                <span class="text-[10px] font-bold uppercase text-base-content/60 block">CURP</span>
                                <span class="font-mono text-[11px] font-semibold text-base-content block truncate">{{ displayCurp }}</span>
                            </div>
                            <div class="bg-base-200/60 rounded-lg p-2">
                                <span class="text-[10px] font-bold uppercase text-base-content/60 block">NSS</span>
                                <span class="font-mono text-[11px] font-semibold text-base-content block truncate">{{ displayNss }}</span>
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

            <!-- Contenedor de la credencial con estilo PVC sindicato -->
            <div
                class="relative rounded-3xl bg-gradient-to-br from-base-100 via-base-100 to-base-200 border-2 border-primary/30 p-6 shadow-2xl overflow-hidden transition-all duration-300 hover:shadow-primary/5"
            >
                <!-- Efecto de escaneo IA -->
                <div
                    v-if="isScanning"
                    class="absolute inset-0 pointer-events-none z-20 motion-safe:animate-pulse bg-gradient-to-b from-primary/10 via-primary/5 to-transparent"
                    aria-hidden="true"
                >
                    <div class="w-full h-1 bg-primary/70 shadow-[0_0_16px_var(--siut-color-primary)] animate-bounce" />
                </div>

                <!-- Patrón decorativo de fondo -->
                <div class="absolute -right-12 -bottom-12 w-44 h-44 rounded-full bg-primary/5 blur-2xl pointer-events-none" />

                <!-- Cabecera de la credencial -->
                <div class="flex items-start justify-between gap-3 pb-4 border-b border-base-300">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-black text-sm text-primary tracking-widest uppercase">SIUT ITSM</span>
                            <span class="text-base-content/30 text-xs">|</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/60">Padrón Oficial</span>
                        </div>
                        <h4 class="text-xs font-bold text-base-content/90 leading-tight mt-0.5">
                            Sindicato Independiente de Trabajadores
                        </h4>
                    </div>

                    <span class="badge badge-primary badge-sm gap-1 font-mono text-[10px] uppercase font-bold shrink-0">
                        <ShieldCheck class="size-3.5" />
                        Aspirante
                    </span>
                </div>

                <!-- Foto y Nombre -->
                <div class="mt-6 flex flex-col items-center text-center">
                    <div class="relative size-28 rounded-2xl overflow-hidden bg-base-200 border-2 border-primary/40 shadow-inner flex items-center justify-center group">
                        <img
                            v-if="photoUrl"
                            :src="photoUrl"
                            alt="Fotografía oficial para credencial"
                            class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex flex-col items-center justify-center text-base-content/30 p-2">
                            <User class="size-12" />
                            <span class="text-[10px] font-medium mt-1">Sin fotografía</span>
                        </div>
                    </div>

                    <div class="mt-4 w-full">
                        <span class="text-[10px] font-bold text-base-content/50 uppercase tracking-wider block">Agremiado(a)</span>
                        <h3 class="font-serif text-lg font-bold text-base-content leading-tight mt-1 line-clamp-2 px-2">
                            {{ displayName }}
                        </h3>
                        <p class="text-xs text-primary font-semibold mt-1 truncate max-w-xs mx-auto">
                            {{ displayCategory }}
                        </p>
                    </div>
                </div>

                <!-- Franja de datos alfanuméricos -->
                <div class="mt-6 space-y-2 pt-4 border-t border-base-200">
                    <div class="flex items-center justify-between p-2 rounded-lg bg-base-200/60">
                        <span class="text-[11px] font-bold uppercase text-base-content/60">CURP:</span>
                        <span class="font-mono text-xs font-bold text-base-content tracking-wider">{{ displayCurp }}</span>
                    </div>

                    <div class="flex items-center justify-between p-2 rounded-lg bg-base-200/60">
                        <span class="text-[11px] font-bold uppercase text-base-content/60">NSS:</span>
                        <span class="font-mono text-xs font-semibold text-base-content tracking-wide">{{ displayNss }}</span>
                    </div>
                </div>

                <!-- Pie de credencial -->
                <div class="mt-6 pt-3 border-t border-dashed border-base-300 flex items-center justify-between text-[10px] text-base-content/50 font-mono">
                    <span>EXPEDIENTE DIGITAL</span>
                    <span>SIUT-{{ new Date().getFullYear() }}</span>
                </div>
            </div>
        </div>
    </aside>
</template>
