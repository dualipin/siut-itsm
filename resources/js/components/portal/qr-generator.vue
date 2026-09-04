<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm p-6">
        <!-- Encabezado del Widget -->
        <div
            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-lg bg-primary-500/10 text-primary-500 dark:bg-primary-500/20 dark:text-red-300 flex items-center justify-center font-bold">
                    <QrCode class="w-5 h-5" />
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        Generador de Códigos QR Institucional
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Genera y descarga códigos QR oficiales con el logotipo del sindicato y pie de página
                        institucional.
                    </p>
                </div>
            </div>

            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-primary-500 dark:bg-red-950/40 dark:text-red-300 border border-red-200/50 dark:border-red-900/50">
                <span class="w-1.5 h-1.5 block rounded-full bg-primary-300"></span>
                OST SIUT ITSM
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Formulario y controles (Columna izquierda) -->
            <div class="lg:col-span-7 space-y-5">
                <!-- Enlaces predefinidos rápidos -->
                <div>
                    <label
                        class="block text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 mb-2">
                        Accesos Rápidos Institucionales
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="preset in availablePresets" :key="preset.url" type="button"
                            @click="selectPreset(preset.url)" :class="[
                                'text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors flex items-center gap-1.5 cursor-pointer',
                                targetUrl === preset.url
                                    ? 'bg-primary-500 text-white border-primary-500 shadow-xs'
                                    : 'bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700'
                            ]">
                            <span>{{ preset.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- Input de URL personalizada -->
                <div>
                    <label for="qr-target-url"
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        URL o Enlace de Destino
                    </label>
                    <div class="relative">
                        <input id="qr-target-url" v-model="targetUrl" type="url"
                            placeholder="https://ejemplo.com/recurso"
                            class="w-full px-3.5 py-2.5 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg shadow-2xs focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:text-white transition-colors" />
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Cualquier enlace público o del portal que desees codificar en alta definición.
                    </p>
                </div>

                <!-- Opciones de personalización rápida -->
                <div
                    class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200/80 dark:border-gray-700 space-y-3">
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider block">
                        Detalles del Estampado
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400 block mb-0.5">Pie de Página:</span>
                            <input v-model="footerText" type="text"
                                class="w-full px-2.5 py-1.5 text-xs bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-md focus:ring-1 focus:ring-primary-500" />
                        </div>
                        <div>
                            <span class="text-gray-500 dark:text-gray-400 block mb-0.5">Color Primario:</span>
                            <div class="flex items-center gap-2">
                                <input v-model="customColor" type="color"
                                    class="w-7 h-7 p-0 rounded-md border border-gray-300 cursor-pointer" />
                                <span class="font-mono text-xs text-gray-600 dark:text-gray-300">{{ customColor
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="pt-2 flex flex-wrap items-center gap-3">
                    <button type="button" @click="handleDownload" :disabled="isGenerating || !isValidUrl"
                        class="px-5 py-2.5 rounded-lg text-sm font-medium bg-primary-500 hover:bg-[#4d0e27] text-white shadow-sm flex items-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                        <Download class="w-4 h-4" />
                        <span>Descargar Imagen (PNG)</span>
                    </button>

                    <button type="button" @click="handleCopyLink" :disabled="!isValidUrl"
                        class="px-4 py-2.5 rounded-lg text-sm font-medium bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2 transition cursor-pointer">
                        <!-- <Check v-if="copied" class="w-4 h-4 text-emerald-600" />
                        <Copy v-else class="w-4 h-4 text-gray-500" /> -->
                        <MorphIcon class="size-4" :icon="copied ? Check : Copy" />
                        <span>{{ copied ? '¡Enlace Copiado!' : 'Copiar Enlace' }}</span>
                    </button>

                    <a v-if="isValidUrl" :href="targetUrl" target="_blank" rel="noopener noreferrer"
                        class="p-2.5 rounded-lg text-gray-500 hover:text-primary-500 dark:hover:text-red-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                        title="Abrir enlace en pestaña nueva">
                        <ExternalLink class="w-4 h-4" />
                    </a>
                </div>

                <div v-if="errorMessage"
                    class="p-3 rounded-lg bg-red-50 dark:bg-red-950/50 border border-red-200 text-red-700 dark:text-red-300 text-xs">
                    {{ errorMessage }}
                </div>
            </div>

            <!-- Vista Previa del Canvas (Columna derecha) -->
            <div class="lg:col-span-5 flex flex-col items-center justify-center">
                <div
                    class="w-full max-w-[320px] sm:max-w-85 aspect-4/5 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 shadow-sm flex flex-col items-center justify-center relative overflow-hidden group">
                    <!-- Overlay de Carga -->
                    <div v-if="isGenerating"
                        class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 backdrop-blur-xs flex flex-col items-center justify-center z-10 transition">
                        <RefreshCw class="w-6 h-6 text-primary-500 animate-spin mb-2" />
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Generando QR...</span>
                    </div>

                    <!-- Contenedor del Canvas de Previsualización -->
                    <div ref="previewContainer"
                        class="w-full h-full flex items-center justify-center [&>canvas]:max-w-full [&>canvas]:max-h-full [&>canvas]:rounded-lg [&>canvas]:shadow-xs">
                        <div v-if="!currentCanvas && !isGenerating" class="text-center p-4 text-gray-400 text-xs">
                            <QrCode class="w-10 h-10 mx-auto mb-2 opacity-40" />
                            Ingresa una URL válida para previsualizar el código QR.
                        </div>
                    </div>
                </div>

                <span class="text-[11px] text-gray-400 mt-2 text-center">
                    Vista previa a escala reducida. La descarga es en alta definición (800x1000 px).
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { QrCode, Download, ExternalLink, RefreshCw } from '@lucide/vue';
import {MorphIcon} from 'morphicons/vue'
import { Check, Copy } from 'lucide'
import { buildQrCanvas, downloadQrCanvas } from '../../services/qr-share';

interface PresetItem {
    label: string;
    url: string;
}

interface Props {
    initialUrl?: string;
    brandLogoUrl?: string;
    brandFooterText?: string;
    presets?: PresetItem[];
}

const props = withDefaults(defineProps<Props>(), {
    initialUrl: '',
    brandLogoUrl: '/assets/img/logo.webp',
    brandFooterText: 'OST SIUT ITSM',
    presets: () => [],
});

const targetUrl = ref(props.initialUrl || (typeof window !== 'undefined' ? window.location.origin : 'https://siut-itsm.mx'));
const footerText = ref(props.brandFooterText);
const customColor = ref(getComputedStyle(document.documentElement).getPropertyValue('--primary-500').trim() || '#611232');
const previewContainer = ref<HTMLDivElement | null>(null);
const currentCanvas = ref<HTMLCanvasElement | null>(null);
const isGenerating = ref(false);
const copied = ref(false);
const errorMessage = ref('');

const availablePresets = computed<PresetItem[]>(() => {
    if (props.presets && props.presets.length > 0) {
        return props.presets;
    }
    const origin = typeof window !== 'undefined' ? window.location.origin : '';
    return [
        { label: 'Portal de Afiliados', url: `${origin}/portal` },
        { label: 'Sitio Web Oficial', url: `${origin}/` },
        { label: 'Transparencia', url: `${origin}/transparencia/normativos` },
        { label: 'Registro Sindical', url: `${origin}/portal/register` },
    ];
});

const isValidUrl = computed(() => {
    if (!targetUrl.value || targetUrl.value.trim() === '') {
        return false;
    }
    try {
        new URL(targetUrl.value);
        return true;
    } catch {
        return targetUrl.value.startsWith('/');
    }
});

const selectPreset = (url: string) => {
    targetUrl.value = url;
};

const renderQr = async () => {
    if (!isValidUrl.value) {
        currentCanvas.value = null;
        if (previewContainer.value) {
            previewContainer.value.innerHTML = '';
        }
        return;
    }

    isGenerating.value = true;
    errorMessage.value = '';

    try {
        const fullUrl = targetUrl.value.startsWith('/')
            ? new URL(targetUrl.value, window.location.origin).toString()
            : targetUrl.value;

        const canvas = await buildQrCanvas(fullUrl, {
            brandLogoUrl: props.brandLogoUrl,
            brandFooterText: footerText.value,
            primaryColor: customColor.value,
        });

        currentCanvas.value = canvas;

        if (previewContainer.value) {
            previewContainer.value.innerHTML = '';
            // Clonamos visualmente para que se adapte con CSS fluidamente
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            canvas.style.objectFit = 'contain';
            previewContainer.value.appendChild(canvas);
        }
    } catch (err: unknown) {
        console.error('Error al generar código QR:', err);
        errorMessage.value = 'No se pudo generar el código QR. Verifica la URL o los recursos.';
    } finally {
        isGenerating.value = false;
    }
};

const handleDownload = () => {
    if (!currentCanvas.value) {
        return;
    }
    const cleanName = footerText.value.toLowerCase().replace(/[^a-z0-9]/g, '-');
    downloadQrCanvas(currentCanvas.value, `qr-${cleanName || 'siut'}.png`);
};

const handleCopyLink = async () => {
    if (!targetUrl.value) {
        return;
    }
    try {
        await navigator.clipboard.writeText(targetUrl.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2500);
    } catch (e) {
        console.error('No se pudo copiar el enlace:', e);
    }
};

// Observar cambios con debounce sutil
let debounceTimer: ReturnType<typeof setTimeout> | null = null;
watch([targetUrl, footerText, customColor], () => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
    debounceTimer = setTimeout(() => {
        renderQr();
    }, 250);
});

onMounted(() => {
    renderQr();
});
</script>
