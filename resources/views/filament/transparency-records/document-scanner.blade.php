<div
    wire:ignore
    x-data="{
        isOpen: false,
        viewMode: 'camera',
        showDiscardConfirm: false,
        cropEditor: null,
        pages: [],
        batchFilter: 'documento',
        pdfFilename: '',
        processingCount: 0,
        isExporting: false,
        errorMessage: null,
        cameraState: 'idle',
        cameraFallbackMessage: null,
        cameraStream: null,
        cameraTrack: null,
        torchSupported: false,
        torchEnabled: false,
        isCapturingFrame: false,

        destroy() {
            this.stopCameraStream();
            this.revokeAllPageUrls();
        },

        async ensureScannerLoaded() {
            if (window.transparencyScanner) {
                return window.transparencyScanner;
            }

            let attempts = 0;
            while (!window.transparencyScanner && attempts < 30) {
                await new Promise((resolve) => setTimeout(resolve, 100));
                attempts++;
            }

            if (!window.transparencyScanner) {
                throw new Error('El módulo del escáner documental aún se está cargando. Intenta de nuevo.');
            }

            return window.transparencyScanner;
        },

        readWireFormMetadata() {
            let referenceNumber = '';
            let name = '';

            try {
                if (typeof $wire !== 'undefined' && typeof $wire.get === 'function') {
                    referenceNumber = String($wire.get('data.reference_number') ?? '');
                    name = String($wire.get('data.name') ?? '');
                }
            } catch (e) {}

            return { referenceNumber, name };
        },

        async openScanner() {
            this.errorMessage = null;
            this.cameraFallbackMessage = null;
            this.showDiscardConfirm = false;
            this.isOpen = true;

            try {
                const scanner = await this.ensureScannerLoaded();
                if (!this.pdfFilename.trim()) {
                    this.pdfFilename = scanner.buildSuggestedPdfFilename(this.readWireFormMetadata());
                }
                await this.openCameraView();
            } catch (err) {
                this.cameraState = 'fallback';
                this.viewMode = 'review';
                this.errorMessage = err.message || 'No fue posible inicializar el escáner.';
            }
        },

        async openCameraView() {
            this.errorMessage = null;
            this.cameraFallbackMessage = null;
            this.viewMode = 'camera';
            this.cameraState = 'starting';
            this.stopCameraStream();

            await this.$nextTick();

            try {
                const scanner = await this.ensureScannerLoaded();
                const result = await scanner.startRearCameraStream();

                if (!this.isOpen || this.viewMode !== 'camera') {
                    if (result.ok) {
                        scanner.stopMediaStream(result.stream);
                    }

                    return;
                }

                if (!result.ok) {
                    this.cameraState = 'fallback';
                    this.cameraFallbackMessage = result.message;
                    this.viewMode = 'review';

                    return;
                }

                this.cameraStream = result.stream;
                this.cameraTrack = result.track;
                this.torchSupported = Boolean(result.torchSupported);
                this.torchEnabled = false;

                result.track.addEventListener('ended', () => {
                    if (this.isOpen && this.viewMode === 'camera') {
                        this.stopCameraStream();
                        this.cameraState = 'fallback';
                        this.cameraFallbackMessage = 'La cámara se desconectó. Puedes reintentar con &quot;Agregar más fotos&quot; o usar &quot;Tomar foto&quot; / &quot;Seleccionar de galería&quot;.';
                        this.viewMode = 'review';
                    }
                }, { once: true });

                const video = this.$refs.cameraVideo;
                if (video) {
                    video.srcObject = result.stream;
                    await video.play().catch(() => {});
                }

                this.cameraState = 'ready';
            } catch (err) {
                this.stopCameraStream();
                this.cameraState = 'fallback';
                this.cameraFallbackMessage = err.message || 'No fue posible abrir el visor continuo. Usa &quot;Tomar foto&quot; o &quot;Seleccionar de galería&quot;.';
                this.viewMode = 'review';
            }
        },

        stopCameraStream() {
            const video = this.$refs?.cameraVideo ?? null;

            if (window.transparencyScanner?.stopMediaStream) {
                window.transparencyScanner.stopMediaStream(this.cameraStream, video);
            } else {
                if (this.cameraStream) {
                    for (const track of this.cameraStream.getTracks()) {
                        try {
                            track.stop();
                        } catch (e) {}
                    }
                }
                if (video) {
                    video.srcObject = null;
                }
            }

            this.cameraStream = null;
            this.cameraTrack = null;
            this.torchSupported = false;
            this.torchEnabled = false;
            this.isCapturingFrame = false;

            if (this.cameraState !== 'fallback') {
                this.cameraState = 'idle';
            }
        },

        switchToReview() {
            this.stopCameraStream();
            this.viewMode = 'review';
        },

        openGalleryFromCamera() {
            this.switchToReview();
            this.$nextTick(() => {
                this.$refs.galleryInput?.click();
            });
        },

        handleVisibilityChange() {
            if (document.hidden) {
                this.stopCameraStream();

                return;
            }

            if (this.isOpen && this.viewMode === 'camera' && !this.cameraStream && this.cameraState === 'idle') {
                this.openCameraView();
            }
        },

        async toggleTorch() {
            if (!this.torchSupported || !this.cameraTrack || !window.transparencyScanner) {
                return;
            }

            this.errorMessage = null;
            const nextState = !this.torchEnabled;

            try {
                this.torchEnabled = await window.transparencyScanner.setTrackTorch(this.cameraTrack, nextState);
            } catch (err) {
                this.errorMessage = 'No se pudo cambiar la linterna en este dispositivo.';
            }
        },

        async captureCameraPage() {
            if (this.cameraState !== 'ready' || this.isCapturingFrame || this.isExporting) {
                return;
            }

            this.errorMessage = null;
            this.isCapturingFrame = true;
            this.processingCount += 1;

            try {
                const scanner = await this.ensureScannerLoaded();
                const blob = await scanner.captureVideoFrameBlob(this.$refs.cameraVideo);
                this.isCapturingFrame = false;

                const pageNumber = this.pages.length + 1;
                const page = await scanner.addPageFromSource(blob, {
                    sourceName: `captura-${pageNumber}.jpg`,
                });

                if (this.isOpen) {
                    this.pages.push(page);
                } else {
                    scanner.revokeScannedPageUrls(page);
                }
            } catch (err) {
                this.errorMessage = err.message || 'No fue posible capturar la página desde la cámara.';
            } finally {
                this.isCapturingFrame = false;
                this.processingCount = Math.max(0, this.processingCount - 1);
            }
        },

        lastPagePreviewUrl() {
            return this.pages.length > 0 ? this.pages[this.pages.length - 1].previewUrl : null;
        },

        normalizeFilenameOnBlur() {
            if (!window.transparencyScanner) {
                return;
            }

            this.pdfFilename = window.transparencyScanner.ensurePdfFilename(
                this.pdfFilename,
                this.readWireFormMetadata(),
            );
        },

        async handleFilesSelected(event) {
            const input = event.target;
            const files = Array.from(input?.files || []);

            if (input) {
                input.value = '';
            }

            if (files.length === 0) {
                return;
            }

            this.errorMessage = null;
            this.processingCount += files.length;

            try {
                const scanner = await this.ensureScannerLoaded();

                for (const file of files) {
                    if (file.type && !file.type.startsWith('image/')) {
                        this.errorMessage = 'Solo se pueden agregar archivos de imagen (JPG, PNG, WebP).';
                        continue;
                    }

                    const page = await scanner.addPageFromSource(file, {
                        sourceName: file.name,
                    });

                    if (this.isOpen) {
                        this.pages.push(page);
                    } else {
                        scanner.revokeScannedPageUrls(page);
                    }
                }
            } catch (err) {
                this.errorMessage = err.message || 'No se pudieron procesar una o más imágenes.';
            } finally {
                this.processingCount = Math.max(0, this.processingCount - files.length);
            }
        },

        removePage(pageId) {
            const index = this.pages.findIndex((page) => page.id === pageId);
            if (index === -1) {
                return;
            }

            const [removed] = this.pages.splice(index, 1);
            if (window.transparencyScanner?.revokeScannedPageUrls) {
                window.transparencyScanner.revokeScannedPageUrls(removed);
            } else {
                if (removed?.previewUrl) {
                    URL.revokeObjectURL(removed.previewUrl);
                }
                if (removed?.originalPreviewUrl) {
                    URL.revokeObjectURL(removed.originalPreviewUrl);
                }
            }
            if (this.cropEditor?.pageId === pageId) {
                this.cropEditor = null;
            }
            this.errorMessage = null;
        },

        revokeAllPageUrls() {
            for (const page of this.pages) {
                if (window.transparencyScanner?.revokeScannedPageUrls) {
                    window.transparencyScanner.revokeScannedPageUrls(page);
                } else {
                    if (page.previewUrl) {
                        URL.revokeObjectURL(page.previewUrl);
                    }
                    if (page.originalPreviewUrl) {
                        URL.revokeObjectURL(page.originalPreviewUrl);
                    }
                }
            }
        },

        openCropEditor(pageId) {
            if (this.isExporting || this.processingCount > 0 || !window.transparencyScanner) {
                return;
            }

            const index = this.pages.findIndex((page) => page.id === pageId);
            if (index === -1) {
                return;
            }

            const page = this.pages[index];
            this.cropEditor = {
                pageId: page.id,
                pageNumber: index + 1,
                originalPreviewUrl: page.originalPreviewUrl,
                quad: window.transparencyScanner.cloneQuad(page.quad),
                activeCorner: null,
                isApplying: false,
                errorMessage: null,
            };
        },

        closeCropEditor() {
            if (this.cropEditor?.isApplying) {
                return;
            }

            this.cropEditor = null;
        },

        startCornerDrag(cornerKey, event) {
            if (!this.cropEditor || this.cropEditor.isApplying) {
                return;
            }

            event.preventDefault();
            this.cropEditor.activeCorner = cornerKey;
            this.cropEditor.errorMessage = null;

            if (event.currentTarget && typeof event.currentTarget.setPointerCapture === 'function') {
                try {
                    event.currentTarget.setPointerCapture(event.pointerId);
                } catch (e) {}
            }

            this.updateActiveCornerFromPointer(event);
        },

        moveCornerDrag(event) {
            if (!this.cropEditor?.activeCorner || this.cropEditor.isApplying) {
                return;
            }

            event.preventDefault();
            this.updateActiveCornerFromPointer(event);
        },

        endCornerDrag() {
            if (this.cropEditor) {
                this.cropEditor.activeCorner = null;
            }
        },

        updateActiveCornerFromPointer(event) {
            const stage = this.$refs.cropStage;
            if (!stage || !this.cropEditor?.activeCorner) {
                return;
            }

            const rect = stage.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) {
                return;
            }

            const x = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
            const y = Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height));
            this.cropEditor.quad[this.cropEditor.activeCorner] = { x, y };
        },

        quadPolygonPoints() {
            const q = this.cropEditor?.quad;
            if (!q) {
                return '';
            }

            return [
                (q.topLeft.x * 100) + ',' + (q.topLeft.y * 100),
                (q.topRight.x * 100) + ',' + (q.topRight.y * 100),
                (q.bottomRight.x * 100) + ',' + (q.bottomRight.y * 100),
                (q.bottomLeft.x * 100) + ',' + (q.bottomLeft.y * 100),
            ].join(' ');
        },

        quadMaskPath() {
            const q = this.cropEditor?.quad;
            if (!q) {
                return '';
            }

            return 'M0,0 H100 V100 H0 Z M'
                + (q.topLeft.x * 100) + ',' + (q.topLeft.y * 100)
                + ' L' + (q.topRight.x * 100) + ',' + (q.topRight.y * 100)
                + ' L' + (q.bottomRight.x * 100) + ',' + (q.bottomRight.y * 100)
                + ' L' + (q.bottomLeft.x * 100) + ',' + (q.bottomLeft.y * 100)
                + ' Z';
        },

        async recomputeEditorPage(quad, closeAfter) {
            if (!this.cropEditor || this.cropEditor.isApplying) {
                return;
            }

            const scanner = await this.ensureScannerLoaded();
            if (!scanner.isValidConvexQuad(quad)) {
                this.cropEditor.errorMessage = 'Las 4 esquinas deben formar un cuadrilátero válido sin cruzarse.';

                return;
            }

            const index = this.pages.findIndex((page) => page.id === this.cropEditor.pageId);
            if (index === -1) {
                this.cropEditor = null;

                return;
            }

            this.cropEditor.isApplying = true;
            this.cropEditor.errorMessage = null;

            try {
                const updated = await scanner.reprocessScannedPage(this.pages[index], quad);
                this.pages.splice(index, 1, updated);

                if (closeAfter) {
                    this.cropEditor = null;
                } else if (this.cropEditor) {
                    this.cropEditor.quad = scanner.cloneQuad(updated.quad);
                }
            } catch (err) {
                if (this.cropEditor) {
                    this.cropEditor.errorMessage = err.message || 'No fue posible aplicar el recorte.';
                }
            } finally {
                if (this.cropEditor) {
                    this.cropEditor.isApplying = false;
                }
            }
        },

        async applyCrop() {
            if (!this.cropEditor) {
                return;
            }

            await this.recomputeEditorPage(this.cropEditor.quad, true);
        },

        async resetCropFrame() {
            if (!this.cropEditor || !window.transparencyScanner) {
                return;
            }

            const fullQuad = window.transparencyScanner.cloneQuad(window.transparencyScanner.FULL_DOCUMENT_QUAD);
            this.cropEditor.quad = fullQuad;
            await this.recomputeEditorPage(fullQuad, false);
        },

        async setPageFilter(pageId, filter, selectEl = null) {
            const page = this.pages.find((item) => item.id === pageId);
            if (!page || page.filter === filter) {
                return;
            }

            if (this.isExporting || this.processingCount > 0) {
                if (selectEl) {
                    selectEl.value = page.filter;
                }

                return;
            }

            this.errorMessage = null;
            this.processingCount += 1;

            try {
                const scanner = await this.ensureScannerLoaded();
                const updated = await scanner.reprocessScannedPage(page, page.quad, { filter });
                const currentIndex = this.pages.findIndex((item) => item.id === pageId);

                if (currentIndex !== -1 && this.isOpen) {
                    this.pages.splice(currentIndex, 1, updated);
                } else {
                    scanner.revokeScannedPageUrls(updated);
                }
            } catch (err) {
                if (selectEl) {
                    selectEl.value = page.filter;
                }
                this.errorMessage = err.message || 'No fue posible aplicar el filtro a la página.';
            } finally {
                this.processingCount = Math.max(0, this.processingCount - 1);
            }
        },

        async applyFilterToAllPages(filter) {
            if (this.isExporting || this.processingCount > 0 || this.pages.length === 0) {
                return;
            }

            const targetIds = this.pages
                .filter((page) => page.filter !== filter)
                .map((page) => page.id);

            if (targetIds.length === 0) {
                return;
            }

            this.errorMessage = null;
            this.processingCount += targetIds.length;

            try {
                const scanner = await this.ensureScannerLoaded();

                for (const pageId of targetIds) {
                    const currentIndex = this.pages.findIndex((item) => item.id === pageId);
                    if (currentIndex === -1 || !this.isOpen) {
                        continue;
                    }

                    const updated = await scanner.reprocessScannedPage(
                        this.pages[currentIndex],
                        this.pages[currentIndex].quad,
                        { filter },
                    );

                    const refreshedIndex = this.pages.findIndex((item) => item.id === pageId);
                    if (refreshedIndex !== -1 && this.isOpen) {
                        this.pages.splice(refreshedIndex, 1, updated);
                    } else {
                        scanner.revokeScannedPageUrls(updated);
                    }
                }
            } catch (err) {
                this.errorMessage = err.message || 'No fue posible aplicar el filtro a todas las páginas.';
            } finally {
                this.processingCount = Math.max(0, this.processingCount - targetIds.length);
            }
        },

        async rotatePage(pageId) {
            if (this.isExporting || this.processingCount > 0) {
                return;
            }

            const page = this.pages.find((item) => item.id === pageId);
            if (!page) {
                return;
            }

            this.errorMessage = null;
            this.processingCount += 1;

            try {
                const scanner = await this.ensureScannerLoaded();
                const nextRotation = scanner.normalizeRotation((page.rotation ?? 0) + 90);
                const updated = await scanner.reprocessScannedPage(page, page.quad, { rotation: nextRotation });
                const currentIndex = this.pages.findIndex((item) => item.id === pageId);

                if (currentIndex !== -1 && this.isOpen) {
                    this.pages.splice(currentIndex, 1, updated);
                } else {
                    scanner.revokeScannedPageUrls(updated);
                }
            } catch (err) {
                this.errorMessage = err.message || 'No fue posible rotar la página.';
            } finally {
                this.processingCount = Math.max(0, this.processingCount - 1);
            }
        },

        movePageBy(fromIndex, delta) {
            if (this.isExporting || this.processingCount > 0 || !window.transparencyScanner) {
                return;
            }

            const toIndex = fromIndex + delta;
            if (toIndex < 0 || toIndex >= this.pages.length) {
                return;
            }

            this.pages = window.transparencyScanner.movePage(this.pages, fromIndex, toIndex);
        },

        requestClose() {
            if (this.isExporting || this.cropEditor?.isApplying) {
                return;
            }

            if (this.pages.length > 0) {
                this.showDiscardConfirm = true;

                return;
            }

            this.forceClose();
        },

        forceClose() {
            this.stopCameraStream();
            this.revokeAllPageUrls();
            this.cropEditor = null;
            this.pages = [];
            this.batchFilter = 'documento';
            this.pdfFilename = '';
            this.showDiscardConfirm = false;
            this.errorMessage = null;
            this.cameraFallbackMessage = null;
            this.viewMode = 'camera';
            this.cameraState = 'idle';
            this.isOpen = false;
        },

        estimatedBytes() {
            if (!window.transparencyScanner || this.pages.length === 0) {
                return 0;
            }

            return window.transparencyScanner.estimatePdfByteLength(this.pages);
        },

        sizeStatus() {
            const bytes = this.estimatedBytes();

            if (!window.transparencyScanner) {
                return {
                    valid: bytes > 0,
                    exceedsLimit: false,
                    byteLength: bytes,
                    formattedSize: '0 KB',
                    formattedMax: '15.00 MB',
                };
            }

            return window.transparencyScanner.validatePdfByteSize(bytes);
        },

        canExport() {
            const status = this.sizeStatus();

            return this.pages.length > 0
                && this.processingCount === 0
                && !this.isExporting
                && status.valid;
        },

        async buildCurrentPdf() {
            const scanner = await this.ensureScannerLoaded();
            const metadata = this.readWireFormMetadata();
            const result = scanner.buildScannedPdfFile(this.pages, this.pdfFilename, metadata);
            this.pdfFilename = result.filename;

            return { scanner, ...result };
        },

        async downloadCopy() {
            if (!this.canExport()) {
                return;
            }

            this.errorMessage = null;
            this.isExporting = true;

            try {
                const { scanner, file } = await this.buildCurrentPdf();
                scanner.triggerLocalPdfDownload(file);
            } catch (err) {
                this.errorMessage = err.message || 'No fue posible generar la descarga del PDF.';
            } finally {
                this.isExporting = false;
            }
        },

        async attachGeneratedPdf() {
            if (!this.canExport()) {
                return;
            }

            this.errorMessage = null;
            this.isExporting = true;
            this.stopCameraStream();

            try {
                const { scanner, file } = await this.buildCurrentPdf();
                await scanner.injectPdfIntoTransparencyUpload(file);
                this.forceClose();
            } catch (err) {
                this.errorMessage = err.message || 'No fue posible adjuntar el PDF generado.';
            } finally {
                this.isExporting = false;
            }
        }
    }"
    x-on:keydown.escape.window="if (isOpen) { if (cropEditor) { closeCropEditor(); } else if (showDiscardConfirm) { showDiscardConfirm = false; } else { requestClose(); } }"
    x-on:pagehide.window="stopCameraStream()"
    x-on:visibilitychange.window="handleVisibilityChange()"
    class="pt-1"
>
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-300 bg-gray-50/70 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/40">
        <div class="space-y-0.5">
            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                Digitalización directa desde cámara o galería
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Captura o selecciona varias páginas y únelas en un único PDF (máximo 15 MB).
            </p>
        </div>

        <button
            type="button"
            x-on:click="openScanner()"
            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
        >
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
            </svg>
            <span>Escanear documento</span>
        </button>
    </div>

    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            role="dialog"
            aria-modal="true"
            aria-labelledby="transparency-scanner-title"
            class="fixed inset-0 z-50 flex flex-col bg-gray-950/80 backdrop-blur-xs"
        >
            <!-- Shared hidden file inputs for gallery multi-select and native capture -->
            <input
                type="file"
                accept="image/*"
                multiple
                x-ref="galleryInput"
                x-on:change="handleFilesSelected($event)"
                class="hidden"
            />
            <input
                type="file"
                accept="image/*"
                capture="environment"
                x-ref="cameraInput"
                x-on:change="handleFilesSelected($event)"
                class="hidden"
            />

            <!-- Continuous rear-camera viewfinder mode -->
            <div
                x-show="viewMode === 'camera'"
                class="flex h-full w-full flex-col bg-gray-950 text-white"
            >
                <header class="flex items-center justify-between gap-3 border-b border-white/10 bg-gray-950/90 px-4 py-3 sm:px-6">
                    <div class="flex items-center gap-2.5">
                        <span class="text-sm font-semibold text-white">Visor de cámara</span>
                        <span
                            class="inline-flex items-center rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-semibold text-white"
                            x-text="pages.length === 1 ? '1 página' : pages.length + ' páginas'"
                        ></span>
                        <template x-if="processingCount > 0">
                            <span class="inline-flex items-center gap-1.5 text-xs text-primary-300">
                                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                                </svg>
                                <span>Procesando...</span>
                            </span>
                        </template>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            x-show="torchSupported"
                            x-on:click="toggleTorch()"
                            :aria-pressed="torchEnabled ? 'true' : 'false'"
                            :class="torchEnabled ? 'bg-amber-400 text-gray-950 hover:bg-amber-300' : 'bg-white/10 text-white hover:bg-white/20'"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                            <span x-text="torchEnabled ? 'Linterna activa' : 'Linterna'"></span>
                        </button>

                        <button
                            type="button"
                            x-on:click="requestClose()"
                            :disabled="isExporting"
                            aria-label="Cerrar escáner"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-white/20 disabled:opacity-50"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                            <span>Cerrar</span>
                        </button>
                    </div>
                </header>

                <div class="relative flex flex-1 items-center justify-center overflow-hidden bg-black">
                    <video
                        x-ref="cameraVideo"
                        autoplay
                        playsinline
                        muted
                        class="h-full w-full object-contain"
                    ></video>

                    <!-- Subtle document framing corner guides -->
                    <div class="pointer-events-none absolute inset-6 sm:inset-10">
                        <div class="absolute top-0 left-0 h-6 w-6 border-t-2 border-l-2 border-white/60"></div>
                        <div class="absolute top-0 right-0 h-6 w-6 border-t-2 border-r-2 border-white/60"></div>
                        <div class="absolute bottom-0 left-0 h-6 w-6 border-b-2 border-l-2 border-white/60"></div>
                        <div class="absolute right-0 bottom-0 h-6 w-6 border-r-2 border-b-2 border-white/60"></div>
                    </div>

                    <template x-if="cameraState === 'starting'">
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-gray-950/80 text-xs text-gray-200">
                            <svg class="h-6 w-6 animate-spin text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                            </svg>
                            <span>Iniciando cámara trasera...</span>
                        </div>
                    </template>

                    <template x-if="errorMessage">
                        <div class="absolute top-4 right-4 left-4 mx-auto max-w-lg rounded-xl border border-red-400/40 bg-red-950/90 px-3.5 py-2.5 text-xs text-red-100 shadow-lg">
                            <span x-text="errorMessage"></span>
                        </div>
                    </template>
                </div>

                <footer class="border-t border-white/10 bg-gray-950/95 px-4 py-4 sm:px-6">
                    <div class="mx-auto flex max-w-2xl items-center justify-between gap-4">
                        <div class="flex w-28 items-center">
                            <template x-if="lastPagePreviewUrl()">
                                <button
                                    type="button"
                                    x-on:click="switchToReview()"
                                    aria-label="Ver última página capturada"
                                    class="relative flex items-center gap-2 rounded-lg border border-white/25 bg-white/10 p-1 transition hover:bg-white/20"
                                >
                                    <img
                                        :src="lastPagePreviewUrl()"
                                        alt="Última captura"
                                        class="h-12 w-9 rounded object-cover"
                                    />
                                    <span
                                        class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-600 px-1.5 text-[11px] font-bold text-white"
                                        x-text="pages.length"
                                    ></span>
                                </button>
                            </template>

                            <template x-if="!lastPagePreviewUrl()">
                                <button
                                    type="button"
                                    x-on:click="openGalleryFromCamera()"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/20"
                                >
                                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    <span>Galería</span>
                                </button>
                            </template>
                        </div>

                        <button
                            type="button"
                            x-on:click="captureCameraPage()"
                            :disabled="cameraState !== 'ready' || isCapturingFrame || isExporting"
                            aria-label="Capturar página"
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full border-4 border-white bg-white/20 p-1.5 transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <span class="h-full w-full rounded-full bg-white"></span>
                        </button>

                        <div class="flex w-28 justify-end">
                            <button
                                type="button"
                                x-on:click="switchToReview()"
                                :disabled="isExporting"
                                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-500 disabled:opacity-50"
                                x-text="'Revisar (' + pages.length + ')'"
                            ></button>
                        </div>
                    </div>
                </footer>
            </div>

            <!-- Batch review & export mode -->
            <div
                x-show="viewMode === 'review'"
                class="flex h-full w-full flex-col bg-white dark:bg-gray-900"
            >
                <!-- Modal header -->
                <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 sm:px-6 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 id="transparency-scanner-title" class="text-sm font-semibold text-gray-900 sm:text-base dark:text-white">
                                Escáner documental
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-text="pages.length === 1 ? '1 página capturada' : pages.length + ' páginas capturadas'"></span>
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:click="requestClose()"
                        :disabled="isExporting"
                        aria-label="Cerrar escáner"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        <span>Cerrar</span>
                    </button>
                </header>

                <!-- Capture / Import toolbar -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 sm:px-6 dark:border-gray-800 dark:bg-gray-950/40">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button
                            type="button"
                            x-on:click="openCameraView()"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-500 disabled:opacity-50"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                            </svg>
                            <span>Agregar más fotos</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="$refs.cameraInput.click()"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                            </svg>
                            <span>Tomar foto</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="$refs.galleryInput.click()"
                            :disabled="isExporting"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                            <span>Seleccionar de galería</span>
                        </button>
                    </div>

                    <template x-if="processingCount > 0">
                        <div class="inline-flex items-center gap-2 text-xs font-medium text-primary-700 dark:text-primary-300">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path>
                            </svg>
                            <span>Optimizando imágenes...</span>
                        </div>
                    </template>
                </div>

                <!-- Main page tray -->
                <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                    <template x-if="cameraFallbackMessage">
                        <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="cameraFallbackMessage"></span>
                        </div>
                    </template>

                    <template x-if="errorMessage">
                        <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="errorMessage"></span>
                        </div>
                    </template>

                    <template x-if="sizeStatus().exceedsLimit">
                        <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <span>
                                El peso estimado (<strong x-text="sizeStatus().formattedSize"></strong>) excede el límite máximo de 15.00 MB. Elimina una o más páginas para poder adjuntar el PDF.
                            </span>
                        </div>
                    </template>

                    <template x-if="pages.length === 0">
                        <div class="flex h-full min-h-64 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-gray-300 bg-gray-50/50 p-8 text-center dark:border-gray-700 dark:bg-gray-800/20">
                            <svg class="mb-3 h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                Aún no hay páginas en el lote
                            </p>
                            <p class="mt-1 max-w-md text-xs text-gray-500 dark:text-gray-400">
                                Usa "Agregar más fotos" para volver al visor continuo, "Tomar foto" con la cámara nativa o "Seleccionar de galería" para importar varias imágenes a la vez.
                            </p>
                        </div>
                    </template>

                    <template x-if="pages.length > 0">
                        <div>
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-2.5 rounded-xl border border-gray-200 bg-gray-50/80 px-3 py-2.5 dark:border-gray-800 dark:bg-gray-900/60">
                                <div class="flex flex-wrap items-center gap-2">
                                    <label for="scanner-batch-filter" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                        Filtro del lote:
                                    </label>
                                    <select
                                        id="scanner-batch-filter"
                                        x-model="batchFilter"
                                        :disabled="isExporting || processingCount > 0"
                                        class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-800 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="documento">Documento</option>
                                        <option value="bw">Blanco y negro</option>
                                        <option value="original">Original</option>
                                    </select>
                                    <button
                                        type="button"
                                        x-on:click="applyFilterToAllPages(batchFilter)"
                                        :disabled="isExporting || processingCount > 0"
                                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Aplicar filtro a todas
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Usa las flechas para reordenar, rota en 90° o toca la hoja para ajustar esquinas.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                <template x-for="(page, index) in pages" :key="page.id">
                                    <div class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-950">
                                        <div class="flex items-center justify-between gap-1 border-b border-gray-100 bg-gray-50/80 px-2 py-1.5 text-[11px] font-semibold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                            <span class="truncate" x-text="'Página ' + (index + 1)"></span>

                                            <div class="flex items-center gap-0.5">
                                                <button
                                                    type="button"
                                                    x-on:click="movePageBy(index, -1)"
                                                    :disabled="index === 0 || isExporting || processingCount > 0"
                                                    :aria-label="'Mover página ' + (index + 1) + ' antes'"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-600 transition hover:bg-gray-200/70 disabled:cursor-not-allowed disabled:opacity-35 dark:text-gray-300 dark:hover:bg-gray-800"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    x-on:click="movePageBy(index, 1)"
                                                    :disabled="index === pages.length - 1 || isExporting || processingCount > 0"
                                                    :aria-label="'Mover página ' + (index + 1) + ' después'"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-600 transition hover:bg-gray-200/70 disabled:cursor-not-allowed disabled:opacity-35 dark:text-gray-300 dark:hover:bg-gray-800"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </button>
                                                <button
                                                    type="button"
                                                    x-on:click="removePage(page.id)"
                                                    :disabled="isExporting"
                                                    :aria-label="'Eliminar página ' + (index + 1)"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-md text-red-600 transition hover:bg-red-50 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>

                                        <button
                                            type="button"
                                            x-on:click="openCropEditor(page.id)"
                                            :disabled="isExporting || processingCount > 0"
                                            :aria-label="'Ajustar recorte de página ' + (index + 1)"
                                            class="relative aspect-3/4 w-full overflow-hidden bg-gray-100 text-left transition focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600 disabled:cursor-not-allowed dark:bg-gray-900"
                                        >
                                            <img
                                                :src="page.previewUrl"
                                                :alt="'Página ' + (index + 1)"
                                                class="h-full w-full object-contain"
                                            />
                                        </button>

                                        <div class="flex items-center justify-between gap-2 border-t border-gray-100 px-2.5 py-1.5 text-[11px] text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                            <span x-text="page.width + '×' + page.height"></span>
                                            <span x-text="window.transparencyScanner ? window.transparencyScanner.formatByteSize(page.jpegBytes.byteLength) : ''"></span>
                                        </div>

                                        <div class="space-y-1.5 border-t border-gray-100 px-2 py-2 dark:border-gray-800">
                                            <div class="flex items-center gap-1.5">
                                                <select
                                                    :value="page.filter"
                                                    x-on:change="setPageFilter(page.id, $event.target.value, $event.target)"
                                                    :disabled="isExporting || processingCount > 0"
                                                    :aria-label="'Filtro de página ' + (index + 1)"
                                                    class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-[11px] font-medium text-gray-700 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                                                >
                                                    <option value="documento">Documento</option>
                                                    <option value="bw">Blanco y negro</option>
                                                    <option value="original">Original</option>
                                                </select>

                                                <button
                                                    type="button"
                                                    x-on:click="rotatePage(page.id)"
                                                    :disabled="isExporting || processingCount > 0"
                                                    :aria-label="'Rotar página ' + (index + 1) + ' 90 grados'"
                                                    title="Rotar 90 grados"
                                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                    </svg>
                                                </button>
                                            </div>

                                            <button
                                                type="button"
                                                x-on:click="openCropEditor(page.id)"
                                                :disabled="isExporting || processingCount > 0"
                                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-[11px] font-semibold text-gray-700 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                            >
                                                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                <span>Ajustar recorte</span>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </main>

                <!-- Export footer -->
                <div class="border-t border-gray-200 bg-gray-50 px-4 py-3.5 sm:px-6 dark:border-gray-800 dark:bg-gray-950/60">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1 space-y-1">
                                <label for="scanner-pdf-filename" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Nombre del archivo PDF
                                </label>
                                <input
                                    id="scanner-pdf-filename"
                                    type="text"
                                    x-model="pdfFilename"
                                    x-on:blur="normalizeFilenameOnBlur()"
                                    placeholder="escaneo.pdf"
                                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                />
                            </div>

                            <div class="rounded-lg border px-3 py-2 text-xs" :class="sizeStatus().exceedsLimit ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300' : 'border-gray-200 bg-white text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'">
                                <span class="block text-[11px] text-gray-500 dark:text-gray-400">Peso estimado</span>
                                <span class="font-semibold" x-text="sizeStatus().formattedSize + ' / ' + sizeStatus().formattedMax"></span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <button
                                type="button"
                                x-on:click="requestClose()"
                                :disabled="isExporting"
                                class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                x-on:click="downloadCopy()"
                                :disabled="!canExport()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                <span>Descargar copia</span>
                            </button>

                            <button
                                type="button"
                                x-on:click="attachGeneratedPdf()"
                                :disabled="!canExport()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <span x-text="isExporting ? 'Generando PDF...' : 'Generar y adjuntar PDF'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive 4-corner perspective crop editor overlay -->
            <template x-if="cropEditor">
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="scanner-crop-editor-title"
                    x-on:pointermove.window="moveCornerDrag($event)"
                    x-on:pointerup.window="endCornerDrag()"
                    x-on:pointercancel.window="endCornerDrag()"
                    class="fixed inset-0 z-60 flex flex-col bg-gray-950 text-white"
                >
                    <header class="flex items-center justify-between gap-4 border-b border-gray-800 px-4 py-3 sm:px-6">
                        <div>
                            <h3 id="scanner-crop-editor-title" class="text-sm font-semibold text-white">
                                Ajustar recorte — Página <span x-text="cropEditor.pageNumber"></span>
                            </h3>
                            <p class="text-xs text-gray-400">
                                Arrastra las 4 esquinas sobre la foto original para corregir la perspectiva.
                            </p>
                        </div>

                        <button
                            type="button"
                            x-on:click="closeCropEditor()"
                            :disabled="cropEditor.isApplying"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-700 bg-gray-900 px-3 py-1.5 text-xs font-semibold text-gray-200 transition hover:bg-gray-800 disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                    </header>

                    <template x-if="cropEditor.errorMessage">
                        <div class="mx-4 mt-3 flex items-start gap-2 rounded-xl border border-red-900/70 bg-red-950/60 p-3 text-xs text-red-200 sm:mx-6">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="cropEditor.errorMessage"></span>
                        </div>
                    </template>

                    <div class="flex flex-1 items-center justify-center overflow-hidden p-6 sm:p-8" style="touch-action: none;">
                        <div
                            x-ref="cropStage"
                            class="relative inline-block select-none"
                            style="touch-action: none;"
                        >
                            <img
                                :src="cropEditor.originalPreviewUrl"
                                :alt="'Original de página ' + cropEditor.pageNumber"
                                draggable="false"
                                class="pointer-events-none block max-h-[65dvh] max-w-full select-none rounded-sm shadow-2xl"
                            />

                            <svg
                                viewBox="0 0 100 100"
                                preserveAspectRatio="none"
                                class="pointer-events-none absolute inset-0 h-full w-full"
                                aria-hidden="true"
                            >
                                <path
                                    :d="quadMaskPath()"
                                    fill="rgba(3, 7, 18, 0.55)"
                                    fill-rule="evenodd"
                                ></path>
                                <polygon
                                    :points="quadPolygonPoints()"
                                    fill="none"
                                    stroke="#ffffff"
                                    stroke-width="2.5"
                                    vector-effect="non-scaling-stroke"
                                ></polygon>
                                <polygon
                                    :points="quadPolygonPoints()"
                                    fill="none"
                                    stroke="#f59e0b"
                                    stroke-dasharray="6 4"
                                    stroke-width="1.5"
                                    vector-effect="non-scaling-stroke"
                                ></polygon>
                            </svg>

                            <template
                                x-for="corner in [
                                    { key: 'topLeft', label: 'Esquina superior izquierda' },
                                    { key: 'topRight', label: 'Esquina superior derecha' },
                                    { key: 'bottomRight', label: 'Esquina inferior derecha' },
                                    { key: 'bottomLeft', label: 'Esquina inferior izquierda' }
                                ]"
                                :key="corner.key"
                            >
                                <button
                                    type="button"
                                    :aria-label="corner.label"
                                    x-on:pointerdown="startCornerDrag(corner.key, $event)"
                                    :style="'touch-action: none; left: ' + (cropEditor.quad[corner.key].x * 100) + '%; top: ' + (cropEditor.quad[corner.key].y * 100) + '%;'"
                                    class="absolute z-10 flex h-11 w-11 -translate-x-1/2 -translate-y-1/2 cursor-grab items-center justify-center rounded-full focus-visible:outline-2 focus-visible:outline-white active:cursor-grabbing"
                                >
                                    <span
                                        class="block h-5 w-5 rounded-full border-2 border-white shadow-md transition-transform"
                                        :class="cropEditor.activeCorner === corner.key ? 'scale-125 bg-amber-400' : 'bg-primary-600'"
                                    ></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-800 bg-gray-900/90 px-4 py-3 sm:px-6">
                        <button
                            type="button"
                            x-on:click="resetCropFrame()"
                            :disabled="cropEditor.isApplying"
                            class="inline-flex items-center rounded-lg border border-gray-700 bg-gray-800 px-3.5 py-2 text-xs font-semibold text-gray-200 transition hover:bg-gray-700 disabled:opacity-50"
                        >
                            Restablecer marco
                        </button>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                x-on:click="closeCropEditor()"
                                :disabled="cropEditor.isApplying"
                                class="inline-flex items-center rounded-lg border border-gray-700 bg-gray-900 px-3.5 py-2 text-xs font-semibold text-gray-300 transition hover:bg-gray-800 disabled:opacity-50"
                            >
                                Cancelar
                            </button>

                            <button
                                type="button"
                                x-on:click="applyCrop()"
                                :disabled="cropEditor.isApplying"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-500 disabled:opacity-50"
                            >
                                <span x-text="cropEditor.isApplying ? 'Aplicando recorte...' : 'Aplicar recorte'"></span>
                            </button>
                        </div>
                    </footer>
                </div>
            </template>

            <!-- Unsaved pages confirmation overlay -->
            <div
                x-show="showDiscardConfirm"
                x-cloak
                class="fixed inset-0 z-60 flex items-center justify-center bg-gray-950/60 p-4"
            >
                <div class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        Descartar páginas escaneadas
                    </h3>
                    <p class="mt-1.5 text-xs text-gray-600 dark:text-gray-400">
                        Tienes páginas capturadas sin adjuntar al registro. Si cierras el escáner se perderán.
                    </p>
                    <div class="mt-4 flex justify-end gap-2">
                        <button
                            type="button"
                            x-on:click="showDiscardConfirm = false"
                            class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Seguir escaneando
                        </button>
                        <button
                            type="button"
                            x-on:click="forceClose()"
                            class="inline-flex items-center rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500"
                        >
                            Descartar y cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    @vite(['resources/js/transparency-scanner.ts'])
</div>
