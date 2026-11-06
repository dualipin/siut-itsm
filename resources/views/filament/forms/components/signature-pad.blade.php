@php
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $statePath }}'),
            isDrawing: false,
            hasDrawn: false,
            ctx: null,
            dpr: window.devicePixelRatio || 1,
            lastWidth: 0,

            init() {
                this.$nextTick(() => {
                    this.setupCanvas();
                    if (this.state) {
                        this.loadFromDataUrl(this.state);
                    }
                });

                this.$watch('state', (value) => {
                    if (!value) {
                        this.clearCanvasOnly();
                    } else if (!this.hasDrawn) {
                        this.loadFromDataUrl(value);
                    }
                });

                if (window.ResizeObserver && this.$refs.canvas) {
                    const ro = new ResizeObserver((entries) => {
                        for (const entry of entries) {
                            const width = Math.round(entry.contentRect.width);
                            if (width > 0 && Math.abs(this.lastWidth - width) > 5) {
                                this.lastWidth = width;
                                this.setupCanvas();
                                if (this.state) {
                                    this.loadFromDataUrl(this.state);
                                }
                            }
                        }
                    });
                    ro.observe(this.$refs.canvas);
                }
            },

            setupCanvas() {
                const canvas = this.$refs.canvas;
                if (!canvas) return;

                const rect = canvas.getBoundingClientRect();
                const width = rect.width > 0 ? rect.width : 450;
                const height = 180;

                canvas.width = Math.round(width * this.dpr);
                canvas.height = Math.round(height * this.dpr);

                this.ctx = canvas.getContext('2d');
                this.ctx.scale(this.dpr, this.dpr);
                this.ctx.lineWidth = 2.5;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#0f172a';
            },

            getPos(e) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                return {
                    x: e.clientX - rect.left,
                    y: e.clientY - rect.top,
                };
            },

            startDrawing(e) {
                if (e.button !== undefined && e.button !== 0) return;
                this.isDrawing = true;
                const pos = this.getPos(e);
                this.ctx.beginPath();
                this.ctx.moveTo(pos.x, pos.y);
            },

            draw(e) {
                if (!this.isDrawing) return;
                const pos = this.getPos(e);
                this.ctx.lineTo(pos.x, pos.y);
                this.ctx.stroke();
                this.hasDrawn = true;
            },

            stopDrawing(e) {
                if (!this.isDrawing) return;
                this.isDrawing = false;
                this.ctx.closePath();
                if (this.hasDrawn) {
                    this.state = this.$refs.canvas.toDataURL('image/png');
                }
            },

            clear() {
                this.clearCanvasOnly();
                this.state = null;
                this.hasDrawn = false;
            },

            clearCanvasOnly() {
                if (!this.ctx || !this.$refs.canvas) return;
                const canvas = this.$refs.canvas;
                this.ctx.clearRect(0, 0, canvas.width / this.dpr, canvas.height / this.dpr);
                this.hasDrawn = false;
            },

            loadFromDataUrl(dataUrl) {
                if (!dataUrl || !this.ctx || !this.$refs.canvas) return;
                const canvas = this.$refs.canvas;
                const img = new Image();
                img.onload = () => {
                    this.clearCanvasOnly();
                    this.ctx.drawImage(img, 0, 0, canvas.width / this.dpr, canvas.height / this.dpr);
                    this.hasDrawn = true;
                };
                img.src = dataUrl;
            }
        }"
        class="space-y-2"
    >
        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-white transition hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600">
            <!-- Canvas container -->
            <div class="relative">
                <canvas
                    x-ref="canvas"
                    x-on:pointerdown="startDrawing($event)"
                    x-on:pointermove="draw($event)"
                    x-on:pointerup="stopDrawing($event)"
                    x-on:pointerleave="stopDrawing($event)"
                    x-on:pointercancel="stopDrawing($event)"
                    class="block w-full cursor-crosshair select-none"
                    style="touch-action: none; height: 180px;"
                ></canvas>

                <!-- Signature baseline guide -->
                <div class="pointer-events-none absolute bottom-6 left-8 right-8 flex items-center justify-between border-b border-gray-200 dark:border-gray-800 pb-1">
                    <span class="text-[11px] font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        Línea de firma autógrafa
                    </span>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 font-mono">
                        {{ auth()->user()?->full_name }}
                    </span>
                </div>
            </div>

            <!-- Footer Toolbar inside canvas card -->
            <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/70 px-4 py-2 dark:border-gray-800 dark:bg-gray-800/40">
                <div class="flex items-center gap-2">
                    <template x-if="hasDrawn || state">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Firma capturada
                        </span>
                    </template>
                    <template x-if="!hasDrawn && !state">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Pendiente de firmar
                        </span>
                    </template>
                </div>

                <button
                    type="button"
                    x-on:click="clear()"
                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-200/60 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    Limpiar trazo
                </button>
            </div>
        </div>
    </div>
</x-dynamic-component>
