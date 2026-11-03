import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                local('Montserrat', {
                    variants: [
                        { src: 'resources/fonts/Montserrat.ttf', weight: 400 },
                        { src: 'resources/fonts/Montserrat.ttf', weight: 500 },
                        { src: 'resources/fonts/Montserrat.ttf', weight: 600 },
                    ],
                    optimizedFallbacks: false,
                }),
                local('Horizon', {
                    variants: [
                        { src: 'resources/fonts/Horizon.otf', weight: 400, style: 'normal' },
                    ],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
