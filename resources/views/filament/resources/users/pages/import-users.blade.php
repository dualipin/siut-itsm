<x-filament-panels::page>
    <div
        x-data="bulkUserImporter({
            chunkUrl: @js(route('portal.users.import.chunk')),
            processUrl: @js(route('portal.users.import.process')),
            cleanupBaseUrl: @js(url('/portal/users/import')),
            csrfToken: @js(csrf_token()),
        })"
        class="space-y-6"
    >
        <x-filament::section
            heading="Archivos para Carga Inicial"
            description="Sube el archivo CSV del padrón y uno o varios archivos ZIP con las carpetas por CURP (ine.* y comprobante_pago.*). La subida se divide automáticamente en fragmentos de 900 KB y se procesa por lotes de 5 usuarios para funcionar sin errores en hosting compartido."
        >
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-950 dark:text-white">
                        1. Archivo CSV del Padrón *
                    </label>
                    <input
                        type="file"
                        accept=".csv,text/csv"
                        x-bind:disabled="running"
                        @change="csvFile = $event.target.files[0] ?? null"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-white/10 dark:bg-white/5 dark:text-white dark:file:bg-white/10"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-show="csvFile" x-text="csvFile ? `${csvFile.name} (${formatBytes(csvFile.size)})` : ''"></p>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-950 dark:text-white">
                        2. Archivos ZIP de Documentos (opcional, uno o varios)
                    </label>
                    <input
                        type="file"
                        accept=".zip,application/zip"
                        multiple
                        x-bind:disabled="running"
                        @change="zipFiles = Array.from($event.target.files ?? [])"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:border-white/10 dark:bg-white/5 dark:text-white dark:file:bg-white/10"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400" x-show="zipFiles.length > 0" x-text="`${zipFiles.length} archivo(s) ZIP seleccionado(s)`"></p>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-950 dark:text-white">
                        3. Contraseña por defecto (solo para usuarios sin CURP)
                    </label>
                    <input
                        type="text"
                        x-model="fallbackPassword"
                        maxlength="18"
                        x-bind:disabled="running"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Quienes tengan CURP recibirán su propia CURP en mayúsculas como contraseña inicial.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-filament::button
                    type="button"
                    icon="heroicon-m-arrow-up-tray"
                    x-on:click="startImport()"
                    x-bind:disabled="running || !csvFile"
                >
                    <span x-show="!running">Iniciar Carga Masiva</span>
                    <span x-show="running" x-text="statusLabel"></span>
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section x-show="started" heading="Progreso de Importación">
            <div class="space-y-4">
                <div>
                    <div class="mb-1 flex justify-between text-sm font-medium text-gray-700 dark:text-gray-300">
                        <span x-text="statusLabel"></span>
                        <span x-text="`${progressPercent}%`"></span>
                    </div>
                    <div class="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div
                            class="h-full bg-primary-600 transition-all duration-200"
                            :style="`width: ${progressPercent}%`"
                        ></div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Usuarios creados</div>
                        <div class="text-xl font-semibold text-gray-950 dark:text-white" x-text="stats.created"></div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Usuarios actualizados</div>
                        <div class="text-xl font-semibold text-gray-950 dark:text-white" x-text="stats.updated"></div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Documentos adjuntados</div>
                        <div class="text-xl font-semibold text-gray-950 dark:text-white" x-text="stats.docsAttached"></div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Errores</div>
                        <div class="text-xl font-semibold text-danger-600 dark:text-danger-400" x-text="errors.length"></div>
                    </div>
                </div>

                <div x-show="fatalError" class="rounded-lg border border-danger-300 bg-danger-50 p-4 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                    <span x-text="fatalError"></span>
                </div>

                <div x-show="errors.length > 0" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-2">Línea</th>
                                <th class="px-4 py-2">Usuario</th>
                                <th class="px-4 py-2">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            <template x-for="(err, idx) in errors" :key="idx">
                                <tr>
                                    <td class="px-4 py-2" x-text="err.line"></td>
                                    <td class="px-4 py-2" x-text="err.name"></td>
                                    <td class="px-4 py-2 text-danger-600 dark:text-danger-400" x-text="err.error"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </x-filament::section>
    </div>

    <script>
        function bulkUserImporter(config) {
            return {
                csvFile: null,
                zipFiles: [],
                fallbackPassword: 'Siut2026!',
                running: false,
                started: false,
                statusLabel: 'Listo',
                progressPercent: 0,
                fatalError: null,
                stats: { created: 0, updated: 0, docsAttached: 0 },
                errors: [],

                formatBytes(bytes) {
                    if (bytes < 1024) return `${bytes} B`;
                    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
                    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
                },

                async startImport() {
                    if (!this.csvFile || this.running) return;

                    this.running = true;
                    this.started = true;
                    this.fatalError = null;
                    this.progressPercent = 0;
                    this.stats = { created: 0, updated: 0, docsAttached: 0 };
                    this.errors = [];

                    const batchId = `imp_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;

                    try {
                        const allFiles = [this.csvFile, ...this.zipFiles];
                        let totalRows = 0;

                        for (let fIdx = 0; fIdx < allFiles.length; fIdx++) {
                            const file = allFiles[fIdx];
                            this.statusLabel = `Subiendo ${file.name} (${fIdx + 1}/${allFiles.length})...`;
                            const res = await this.uploadFileInChunks(batchId, `file_${fIdx}`, file);
                            if (typeof res.total_rows === 'number') {
                                totalRows = res.total_rows;
                            }
                            this.progressPercent = Math.round(((fIdx + 1) / allFiles.length) * 40);
                        }

                        if (totalRows === 0) {
                            throw new Error('El archivo CSV no contiene filas de usuarios para importar.');
                        }

                        const batchSize = 5;
                        for (let offset = 0; offset < totalRows; offset += batchSize) {
                            const currentEnd = Math.min(offset + batchSize, totalRows);
                            this.statusLabel = `Procesando usuarios ${offset + 1} - ${currentEnd} de ${totalRows}...`;

                            const response = await fetch(config.processUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': config.csrfToken,
                                },
                                body: JSON.stringify({
                                    batch_id: batchId,
                                    offset: offset,
                                    limit: batchSize,
                                    fallback_password: this.fallbackPassword || 'Siut2026!',
                                }),
                            });

                            if (!response.ok) {
                                const text = await response.text();
                                throw new Error(`Error al procesar lote (${response.status}): ${text}`);
                            }

                            const data = await response.json();
                            this.stats.created += data.created ?? 0;
                            this.stats.updated += data.updated ?? 0;
                            this.stats.docsAttached += data.docs_attached ?? 0;
                            if (Array.isArray(data.errors)) {
                                this.errors.push(...data.errors);
                            }

                            this.progressPercent = 40 + Math.round((currentEnd / totalRows) * 60);
                        }

                        this.statusLabel = 'Importación completada';
                        this.progressPercent = 100;
                    } catch (err) {
                        this.fatalError = err.message || 'Ocurrió un error inesperado durante la importación.';
                        this.statusLabel = 'Importación detenida con errores';
                    } finally {
                        await fetch(`${config.cleanupBaseUrl}/${batchId}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                            },
                        }).catch(() => {});
                        this.running = false;
                    }
                },

                async uploadFileInChunks(batchId, fileId, file) {
                    const chunkSize = 900 * 1024; // 900 KB
                    const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
                    let lastResult = {};

                    for (let i = 0; i < totalChunks; i++) {
                        const start = i * chunkSize;
                        const end = Math.min(file.size, start + chunkSize);
                        const blob = file.slice(start, end);

                        const form = new FormData();
                        form.append('batch_id', batchId);
                        form.append('file_id', fileId);
                        form.append('chunk_index', String(i));
                        form.append('total_chunks', String(totalChunks));
                        form.append('original_name', file.name);
                        form.append('chunk', blob, `${file.name}.part`);

                        const response = await fetch(config.chunkUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrfToken,
                            },
                            body: form,
                        });

                        if (!response.ok) {
                            const text = await response.text();
                            throw new Error(`Error subiendo ${file.name} parte ${i + 1}/${totalChunks}: ${text}`);
                        }

                        lastResult = await response.json();
                    }

                    return lastResult;
                },
            };
        }
    </script>
</x-filament-panels::page>
