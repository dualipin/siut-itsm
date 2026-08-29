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
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            fonts: [
                google('Fraunces'),
                google('Inter'),
                google('JetBrains Mono'),
                google('Spline Sans'),
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
