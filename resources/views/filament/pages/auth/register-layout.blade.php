<x-filament-panels::layout.base :livewire="$livewire">
    <div class="custom-auth-wrapper min-h-screen w-full flex flex-col lg:flex-row bg-slate-50 dark:bg-zinc-950">
        <!-- Visual / Branding Side (Left on Desktop, Hidden on Mobile/Tablet) -->
        <div class="hidden lg:flex lg:w-5/12 xl:w-4/12 relative flex-col justify-between p-10 xl:p-14 text-white overflow-hidden bg-slate-900 select-none">
            <!-- Background Image with Overlay -->
            <div
                class="absolute inset-0 bg-cover bg-center z-0 scale-105 transition-transform duration-1000"
                style="background-image: url('{{ asset('assets/images/login-background.jpg') }}');"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950/95 via-red-950/85 to-zinc-950/95 z-10 backdrop-blur-[2px]"></div>

            <!-- Top Header in Left Panel -->
            <div class="relative z-20 flex items-center gap-3.5">
                <img
                    class="h-12 w-12 rounded-full object-cover ring-2 ring-white/30 shadow-lg"
                    src="{{ asset('assets/img/logo.webp') }}"
                    alt="Logo SIUT"
                />
                <div>
                    <h2 class="text-base font-bold tracking-wider text-white uppercase leading-tight">
                        SIUT
                    </h2>
                    <p class="text-xs text-red-200/80 font-medium">
                        Sindicato Independiente de Trabajadores
                    </p>
                </div>
            </div>

            <!-- Middle Narrative -->
            <div class="relative z-20 my-auto py-10 space-y-6">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 backdrop-blur-md text-xs font-semibold text-red-100">
                    <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Padrón de Agremiados Digital
                </div>

                <div class="space-y-3">
                    <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight">
                        Construyendo un sindicato <span class="text-transparent bg-clip-text bg-gradient-to-r from-red-300 via-white to-red-200">fuerte y transparente</span>.
                    </h1>
                    <p class="text-sm xl:text-base text-slate-200 leading-relaxed font-normal">
                        Al incorporarte a SIUT, cuentas con representación laboral, seguimiento digital de trámites y acceso a todos tus beneficios sindicales.
                    </p>
                </div>

                <!-- Feature Badges -->
                <div class="space-y-3.5 pt-2">
                    <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm">
                        <div class="p-2 rounded-lg bg-red-500/20 text-red-200 shrink-0">
                            <x-filament::icon icon="heroicon-m-shield-check" class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Respaldo y Asesoría Laboral</h4>
                            <p class="text-xs text-slate-300">Acompañamiento legal y representación sindical permanente.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm">
                        <div class="p-2 rounded-lg bg-red-500/20 text-red-200 shrink-0">
                            <x-filament::icon icon="heroicon-m-document-text" class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Expediente y Trámites Digitales</h4>
                            <p class="text-xs text-slate-300">Solicitudes, prestaciones y consultas directamente en el portal.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3.5 p-3.5 rounded-xl bg-white/5 border border-white/10 backdrop-blur-sm">
                        <div class="p-2 rounded-lg bg-red-500/20 text-red-200 shrink-0">
                            <x-filament::icon icon="heroicon-m-eye" class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-sm font-semibold text-white">Transparencia Total</h4>
                            <p class="text-xs text-slate-300">Rendición de cuentas e informes financieros al alcance de todos.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer in Left Panel -->
            <div class="relative z-20 pt-4 border-t border-white/10 flex items-center justify-between text-xs text-slate-400">
                <span>© {{ date('Y') }} {{ config('app.name') }}</span>
                <span class="flex items-center gap-1">
                    <x-filament::icon icon="heroicon-m-lock-closed" class="w-3.5 h-3.5 text-slate-400" />
                    Plataforma Segura
                </span>
            </div>
        </div>

        <!-- Form Side (Right on Desktop, Full Width on Mobile) -->
        <div class="flex-1 flex flex-col justify-start items-center px-4 py-8 sm:px-8 lg:px-12 xl:px-16 overflow-y-auto bg-white dark:bg-zinc-900 border-l border-slate-200/80 dark:border-zinc-800">
            <div class="w-full max-w-2xl my-auto py-6">
                <!-- Mobile/Tablet Header with Logo -->
                <div class="flex lg:hidden items-center gap-3 mb-6 pb-4 border-b border-slate-200 dark:border-zinc-800">
                    <img
                        class="h-10 w-10 rounded-full object-cover ring-2 ring-red-600/30"
                        src="{{ asset('assets/img/logo.webp') }}"
                        alt="Logo SIUT"
                    />
                    <div>
                        <h2 class="text-sm font-bold tracking-wide uppercase text-slate-900 dark:text-white leading-tight">
                            {{ config('app.name') }}
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Portal de Registro Sindical
                        </p>
                    </div>
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
