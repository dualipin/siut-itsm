@php
    $statePath = $getStatePath();
    $expectedCurp = $getExpectedCurp();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            certificate: $wire.$entangle('efirma_certificate'),
            originalChain: $wire.$entangle('efirma_original_chain'),
            seal: $wire.$entangle('efirma_seal'),
            signedTermsHash: $wire.$entangle('signed_terms_hash'),
            signatureData: $wire.$entangle('signature_data'),
            signatureType: $wire.$entangle('signature_type'),
            metadata: $wire.$entangle('efirma_metadata'),

            expectedCurp: @js($expectedCurp),
            cerFile: null,
            keyFile: null,
            password: '',
            showPassword: false,
            loading: false,
            errorMessage: null,
            preview: null,

            init() {
                if (this.metadata && typeof this.metadata === 'string') {
                    try {
                        this.preview = JSON.parse(this.metadata);
                    } catch (e) {}
                }
            },

            isSigned() {
                return Boolean(this.seal && this.certificate && this.originalChain);
            },

            async handleCer(e) {
                const file = e.target.files?.[0];
                this.cerFile = file || null;
                this.errorMessage = null;
                this.preview = null;

                if (!file) return;

                try {
                    await this.ensureLibraryLoaded();
                    const bin = await window.efirmaSigner.readFileAsBinary(file);
                    const { preview } = window.efirmaSigner.parseCertificate(bin);
                    this.preview = preview;

                    if (this.expectedCurp && preview.curp && preview.curp.toUpperCase() !== this.expectedCurp.toUpperCase()) {
                        this.errorMessage = 'Atención: El CURP de este certificado (' + preview.curp + ') no coincide con tu CURP registrado (' + this.expectedCurp + ').';
                    }
                } catch (err) {
                    this.errorMessage = err.message || 'Error al analizar el certificado digital.';
                }
            },

            handleKey(e) {
                const file = e.target.files?.[0];
                this.keyFile = file || null;
                this.errorMessage = null;
            },

            async ensureLibraryLoaded() {
                if (window.efirmaSigner) return;
                let tries = 0;
                while (!window.efirmaSigner && tries < 20) {
                    await new Promise((r) => setTimeout(r, 100));
                    tries++;
                }
                if (!window.efirmaSigner) {
                    throw new Error('El módulo criptográfico de e.firma se está cargando. Por favor intenta de nuevo en un segundo.');
                }
            },

            async sign() {
                this.errorMessage = null;
                this.loading = true;

                try {
                    if (!this.cerFile) throw new Error('Por favor selecciona tu archivo de certificado digital (.cer)');
                    if (!this.keyFile) throw new Error('Por favor selecciona tu archivo de clave privada (.key)');
                    if (!this.password) throw new Error('Por favor ingresa la contraseña de tu clave privada');

                    await this.ensureLibraryLoaded();

                    const termsHash = await $wire.getCurrentTermsHash();
                    if (!termsHash) {
                        throw new Error('No fue posible calcular el identificador de términos de la solicitud.');
                    }

                    const result = await window.efirmaSigner.signEfirma({
                        cerFile: this.cerFile,
                        keyFile: this.keyFile,
                        password: this.password,
                        termsHash: termsHash,
                        expectedCurp: this.expectedCurp,
                    });

                    // Assign entangled Livewire state
                    this.certificate = result.certificateBase64;
                    this.originalChain = result.originalChain;
                    this.seal = result.sealBase64;
                    this.signedTermsHash = result.termsHash;
                    this.signatureData = 'efirma:' + result.sealBase64;
                    this.signatureType = 'efirma';

                    const meta = {
                        rfc: result.rfc,
                        curp: result.curp,
                        signerName: result.signerName,
                        serial: result.serial,
                        validFrom: result.validFrom,
                        validTo: result.validTo,
                    };
                    this.metadata = JSON.stringify(meta);
                    this.preview = meta;

                    // Security: Wipe password and inputs from memory and DOM immediately
                    this.password = '';
                    this.keyFile = null;
                    this.cerFile = null;
                    if (this.$refs.keyInput) this.$refs.keyInput.value = '';
                    if (this.$refs.cerInput) this.$refs.cerInput.value = '';
                    if (this.$refs.passwordInput) this.$refs.passwordInput.value = '';

                } catch (err) {
                    this.errorMessage = err.message || 'Ocurrió un error al firmar con e.firma.';
                } finally {
                    this.loading = false;
                }
            },

            clearSignature() {
                this.certificate = null;
                this.originalChain = null;
                this.seal = null;
                this.signedTermsHash = null;
                this.signatureData = null;
                this.metadata = null;
                this.preview = null;
                this.password = '';
                this.cerFile = null;
                this.keyFile = null;
                this.errorMessage = null;

                if (this.$refs.keyInput) this.$refs.keyInput.value = '';
                if (this.$refs.cerInput) this.$refs.cerInput.value = '';
                if (this.$refs.passwordInput) this.$refs.passwordInput.value = '';
            }
        }"
        class="space-y-4"
    >
        <!-- Signed state view -->
        <template x-if="isSigned()">
            <div class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5 dark:border-emerald-900/50 dark:bg-emerald-950/20 shadow-sm transition">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-sm">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-emerald-900 dark:text-emerald-200">
                                    Solicitud Firmada Electrónicamente con e.firma
                                </span>
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    Válida
                                </span>
                            </div>
                            <p class="text-xs text-emerald-700 dark:text-emerald-300/90 mt-0.5">
                                Certificado emitido por el Servicio de Administración Tributaria (SAT)
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:click="clearSignature()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100/60 transition dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200 dark:hover:bg-emerald-900/70"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Cambiar e.firma
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 border-t border-emerald-200/70 pt-3 text-xs text-emerald-900 dark:border-emerald-900/40 dark:text-emerald-200">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">Titular</span>
                        <span class="font-semibold" x-text="preview?.signerName || 'Agremiado'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">RFC / CURP</span>
                        <span class="font-mono font-medium" x-text="(preview?.rfc || '') + ' • ' + (preview?.curp || '')"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">No. de Serie</span>
                        <span class="font-mono font-medium" x-text="preview?.serial || 'Desconocido'"></span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-medium">Sello Digital</span>
                        <span class="font-mono text-[10px] truncate block text-emerald-700 dark:text-emerald-300" x-text="seal ? (seal.substring(0, 24) + '...') : ''"></span>
                    </div>
                </div>
            </div>
        </template>

        <!-- Unsigned capture view -->
        <template x-if="!isSigned()">
            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/40 dark:text-primary-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                                Firma Local con Certificados del SAT
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Su clave privada (<code class="text-[11px]">.key</code>) y su contraseña se procesan exclusivamente en la memoria de este navegador. <strong>Nunca</strong> se transfieren ni almacenan en nuestros servidores.
                            </p>
                        </div>
                    </div>

                    <!-- Decoupled Client-Side File Inputs (wire:ignore guarantees no Livewire upload) -->
                    <div wire:ignore class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <!-- Certificado (.cer) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                Certificado Digital (<span class="font-mono text-primary-600 dark:text-primary-400">.cer</span>)
                            </label>
                            <input
                                type="file"
                                accept=".cer"
                                x-ref="cerInput"
                                x-on:change="handleCer($event)"
                                class="block w-full text-xs text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-800 dark:file:text-gray-300 dark:hover:file:bg-gray-700 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 focus:outline-none"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Archivo público de e.firma emitido por el SAT
                            </p>
                        </div>

                        <!-- Clave Privada (.key) - Purely Client-Side -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                Clave Privada (<span class="font-mono text-primary-600 dark:text-primary-400">.key</span>)
                            </label>
                            <input
                                type="file"
                                accept=".key"
                                x-ref="keyInput"
                                x-on:change="handleKey($event)"
                                class="block w-full text-xs text-gray-700 dark:text-gray-300 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-800 dark:file:text-gray-300 dark:hover:file:bg-gray-700 cursor-pointer rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40 focus:outline-none"
                            />
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Solo se lee localmente para generar el sello digital
                            </p>
                        </div>

                        <!-- Contraseña de Clave Privada - Purely Client-Side -->
                        <div class="sm:col-span-2 space-y-1.5 pt-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                Contraseña de la Clave Privada
                            </label>
                            <div class="relative">
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    x-ref="passwordInput"
                                    x-model="password"
                                    placeholder="Ingrese la contraseña de su e.firma"
                                    autocomplete="off"
                                    class="block w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 pr-10"
                                />
                                <button
                                    type="button"
                                    x-on:click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <template x-if="!showPassword">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </template>
                                    <template x-if="showPassword">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>
                                    </template>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Certificate Preview Card (Instant Local Validation) -->
                    <template x-if="preview">
                        <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-3.5 text-xs dark:border-gray-700/60 dark:bg-gray-800/40 space-y-2">
                            <div class="flex items-center justify-between border-b border-gray-200/60 pb-1.5 dark:border-gray-700/40">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">
                                    Datos del Certificado Detectado
                                </span>
                                <template x-if="!preview.isExpired && !preview.isCsd">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Vigente
                                    </span>
                                </template>
                                <template x-if="preview.isExpired">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-600 dark:text-red-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                        Vencido
                                    </span>
                                </template>
                                <template x-if="preview.isCsd">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                        CSD (No admitido)
                                    </span>
                                </template>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-gray-600 dark:text-gray-400 text-[11px]">
                                <div>Titular: <strong class="text-gray-900 dark:text-white" x-text="preview.signerName"></strong></div>
                                <div>RFC: <strong class="text-gray-900 dark:text-white" x-text="preview.rfc"></strong></div>
                                <div>CURP: <strong class="text-gray-900 dark:text-white" x-text="preview.curp"></strong></div>
                                <div class="sm:col-span-2">Serie: <strong class="font-mono text-gray-900 dark:text-white" x-text="preview.serial"></strong></div>
                                <div>Vigencia: <span x-text="preview.validFrom + ' al ' + preview.validTo"></span></div>
                            </div>
                        </div>
                    </template>

                    <!-- Error Alert -->
                    <template x-if="errorMessage">
                        <div class="flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/30 dark:text-red-300">
                            <svg class="h-4 w-4 shrink-0 mt-0.5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                            <span x-text="errorMessage"></span>
                        </div>
                    </template>

                    <!-- Action Button -->
                    <div class="pt-2 flex justify-end">
                        <button
                            type="button"
                            x-on:click="sign()"
                            :disabled="loading || !cerFile || !keyFile || !password"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <template x-if="loading">
                                <svg class="animate-spin -ml-0.5 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <template x-if="!loading">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </template>
                            <span x-text="loading ? 'Validando y Sellando...' : 'Firmar Solicitud con e.firma'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @vite(['resources/js/efirma-signer.ts'])
</x-dynamic-component>
