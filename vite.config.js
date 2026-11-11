import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Tailwind runs as a Vite plugin. An empty PostCSS config stops Vite from picking up a
    // postcss.config.js further up the tree (a git worktree sits inside the main checkout).
    css: {
        postcss: {},
    },
});
