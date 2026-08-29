import { defineConfig } from 'vite';
import inertia from '@inertiajs/vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
                'resources/js/island.ts',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            fonts: [
                google('Fraunces', {weights: [400, 500, 600, 700]}),
                google('Inter', {weights: [400, 500, 600, 700]}),
                google('JetBrains Mono', {weights: [400, 500, 600, 700]}),
                google('Spline Sans', {weights: [400, 500, 600, 700]}),
            ],
        }),
        tailwindcss(),
        inertia(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                }
            }
        })
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    resolve: {
        tsconfigPaths: true,
    }
});
