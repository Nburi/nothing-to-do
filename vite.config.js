import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            // Browser-automation scratch folder. A browser holds files in it open
            // (e.g. an extension .crx), and on Windows the watcher's attempt to
            // watch a locked file throws EBUSY and takes the whole dev server down.
            ignored: ['**/.playwright-mcp/**'],
        },
    },
});
