import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Los assets se publican bajo /app/build para que el proxy
            // (location ~ ^/app(/|$)) los reenvíe a Laravel detrás del subdominio.
            buildDirectory: 'app/build',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Nombres fijos (sin hash) para que el build siempre genere
                // los mismos archivos: assets/app.js, assets/app.css, etc.
                // El entry app.js se fuerza por nombre porque comparte el
                // base "app" con app.css y Rollup lo renombraría a app2.js.
                entryFileNames: (chunk) =>
                    chunk.facadeModuleId?.endsWith('resources/js/app.js')
                        ? 'assets/app.js'
                        : 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
        },
    },
});
