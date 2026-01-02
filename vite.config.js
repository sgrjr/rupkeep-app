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
    build: {
        rollupOptions: {
            onwarn(warning, warn) {
                // Suppress warnings about unresolved font files (they're resolved at runtime)
                if (warning.message && warning.message.includes("didn't resolve at build time")) {
                    return;
                }
                // Use default warning handler for other warnings
                warn(warning);
            },
        },
    },
});
