import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Don't watch every Blade/PHP file — that retriggers Vite on
            // every Octane/view compile and burns heap.
            refresh: [
                'resources/views/**',
                'resources/js/**',
                'app/Livewire/**',
            ],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 6173,
        strictPort: true,
        hmr: {
            host: '192.168.6.71',
        },
        watch: {
            ignored: [
                '**/vendor/**',
                '**/node_modules/**',
                '**/.git/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
                '**/public/hot',
            ],
        },
    },
});
