<x-filament-widgets::widget>
    <div
        data-vue="portal/qr-generator"
        data-props="{{ json_encode([
            'initialUrl' => url('/portal'),
            'brandLogoUrl' => asset('assets/img/logo.webp'),
            'brandFooterText' => config('syndicate.acronym', 'OST SIUT ITSM'),
            'presets' => [
                ['label' => 'Portal de Afiliados', 'url' => url('/portal')],
                ['label' => 'Sitio Web Oficial', 'url' => url('/')],
                ['label' => 'Transparencia', 'url' => url('/transparencia/normativos')],
                ['label' => 'Registro Sindical', 'url' => url('/portal/register')],
            ],
        ]) }}"
    >
    </div>

    @vite(['resources/js/island.ts'])
</x-filament-widgets::widget>
