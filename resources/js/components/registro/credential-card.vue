<script setup lang="ts">
import { User } from '@lucide/vue'

interface Props {
    displayName: string
    displayNss: string
    displayCategory: string
    photoUrl?: string | null
    qrSrc?: string | null
    isScanning?: boolean
    backgroundImageUrl?: string
    logoTecnmUrl?: string
    logoItsmUrl?: string
    logoSiutUrl?: string
    website?: string
}

withDefaults(defineProps<Props>(), {
    photoUrl: null,
    qrSrc: null,
    isScanning: false,
    backgroundImageUrl: '/assets/images/background-id.png',
    logoTecnmUrl: '/assets/img/logo/logo-tecnm.png',
    logoItsmUrl: '/assets/img/logo/logo-itsm.jpg',
    logoSiutUrl: '/assets/images/logo.webp',
    website: 'www.siutitsm.com.mx',
})
</script>

<template>
    <div
        class="relative aspect-[54/85.6] w-full max-w-[280px] select-none overflow-hidden rounded-2xl border border-base-300 bg-white text-center shadow-xl"
    >
        <!-- Arco superior -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 z-[1] h-[41%] bg-cover bg-top bg-no-repeat"
            :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
        />

        <!-- Arco inferior invertido -->
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 z-[2] h-[41%] rotate-180 bg-cover bg-bottom bg-no-repeat"
            :style="{ backgroundImage: `url(${backgroundImageUrl})` }"
        />

        <!-- Efecto de escaneo IA -->
        <div
            v-if="isScanning"
            class="pointer-events-none absolute inset-0 z-20 bg-gradient-to-b from-primary/10 via-primary/5 to-transparent motion-safe:animate-pulse"
            aria-hidden="true"
        >
            <div class="h-1 w-full animate-bounce bg-primary/70 shadow-[0_0_12px_var(--siut-color-primary)]" />
        </div>

        <!-- Contenido del anverso -->
        <div class="relative z-10 flex h-full flex-col items-center px-4">
            <div class="flex w-full flex-col items-center pt-[66px]">
                <!-- Foto circular -->
                <div
                    class="flex size-[90px] shrink-0 items-center justify-center overflow-hidden rounded-full border-[4px] border-[#2E3A6B] bg-white"
                >
                    <img
                        v-if="photoUrl"
                        :src="photoUrl"
                        alt="Foto"
                        class="size-full object-cover"
                    />
                    <div v-else class="flex flex-col items-center justify-center text-[9px] font-medium leading-none text-base-content/40">
                        <User class="mb-1 size-6 text-base-content/30" />
                        <span>SIN FOTO</span>
                    </div>
                </div>

                <!-- Nombre y cargo -->
                <div class="mt-[18px] max-w-full px-1 text-[17px] font-extrabold uppercase leading-[1.05] tracking-tight text-[#2E3A6B] break-words">
                    {{ displayName }}
                </div>
                <div class="mt-[4px] text-[10px] font-normal uppercase tracking-wide text-[#2E3A6B]">
                    {{ displayCategory }}
                </div>

                <!-- QR -->
                <div class="mt-[9px] flex h-[47px] w-[150px] items-center justify-center rounded-[10px] bg-[#2E3A6B]">
                    <img
                        v-if="qrSrc"
                        :src="qrSrc"
                        alt="Código QR"
                        class="size-[38px] rounded-[2px] bg-white"
                    />
                    <span v-else class="text-[8px] font-semibold text-white/70">SIN QR</span>
                </div>

                <!-- Número de IMSS -->
                <div class="mt-[6px] text-[11px] font-medium uppercase tracking-wide text-[#2E3A6B]">
                    NO. IMSS <span class="font-semibold">{{ displayNss }}</span>
                </div>

                <!-- Afiliación -->
                <!-- ponytail: texto fijo para replicar el PDF original; usar prop si cambia la razón social -->
                <div class="mt-[10px] text-center leading-[1.15] text-[#2E3A6B]">
                    <div class="text-[10px] font-normal uppercase">MIEMBRO ACTIVO DEL</div>
                    <div class="text-[10.5px] font-bold uppercase">
                        SINDICATO ÚNICO DE TRABAJADORES<br />
                        DEL INSTITUTO TECNOLÓGICO<br />
                        SUPERIOR DE MACUSPANA.
                    </div>
                    <div class="mt-[5px] text-[10.5px] font-bold uppercase">REG. RS-033/2019.</div>
                </div>
            </div>

            <!-- Pie con sitio y logos -->
            <div class="mt-auto flex w-full flex-col items-center pb-1">
                <div class="mb-[2px] text-[10px] font-semibold text-[#2E3A6B]">
                    {{ website }}
                </div>
                <div class="flex w-full items-end justify-between px-1">
                    <img :src="logoTecnmUrl" alt="TecNM" class="h-[32px] w-auto max-w-[34px] object-contain" />
                    <img :src="logoItsmUrl" alt="ITSM" class="h-[44px] w-auto max-w-[74px] rounded-full object-cover" />
                    <img :src="logoSiutUrl" alt="SIUT" class="h-[38px] w-auto max-w-[42px] rounded-full object-contain" />
                </div>
            </div>
        </div>
    </div>
</template>
