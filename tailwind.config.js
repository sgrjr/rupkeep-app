import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            // The app draws almost every card and container edge with
            // border-slate-200. At Tailwind's #e2e8f0 that is roughly 1.24:1
            // against white -- present in the DOM and invisible to the eye
            // (TASK-394). Redefining it here lifts all 400-odd container
            // borders at once without touching 52 blade files.
            //
            // NOTE: this makes the class name no longer match Tailwind's own
            // slate-200. Only borders are affected -- text-slate-200 and
            // bg-slate-200 keep their normal values. Form controls do NOT rely
            // on this: they carry an explicit border-slate-400, because they
            // are UI component boundaries and WCAG wants 3:1 there.
            borderColor: {
                slate: {
                    200: '#cbd5e1',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                orange: {
                    50: '#fff7ed',
                    100: '#ffedd5',
                    200: '#fed7aa',
                    300: '#fdba74',
                    400: '#fb923c',
                    500: '#f9b104', // Primary orange
                    600: '#ea580c',
                    700: '#c2410c',
                    800: '#9a3412',
                    900: '#7c2d12',
                },
            },
        },
    },

    plugins: [forms, typography],
};
