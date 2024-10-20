import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/default_dashboard_theme.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
