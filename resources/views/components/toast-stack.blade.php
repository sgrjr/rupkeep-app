@props(['toasts' => []])

@if(!empty($toasts))
    <div
        x-data="{
            toasts: {{ json_encode($toasts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }},
            remove(index) {
                this.toasts.splice(index, 1);
            },
            classes(type) {
                switch (type) {
                    case 'success':
                        return 'border-emerald-200 bg-emerald-50 text-emerald-700';
                    case 'error':
                        return 'border-rose-200 bg-rose-50 text-rose-700';
                    case 'warning':
                        return 'border-amber-200 bg-amber-50 text-amber-700';
                    default:
                        return 'border-slate-200 bg-white text-slate-700';
                }
            },
            icon(type) {
                switch (type) {
                    case 'success':
                        return 'M5 13l4 4L19 7';
                    case 'error':
                        return 'M6 18L18 6M6 6l12 12';
                    case 'warning':
                        return 'M12 9v4m0 4h.01M10.29 3.86l-8.41 14.48A1 1 0 0 0 2.76 20h18.48a1 1 0 0 0 .88-1.66L13.71 3.86a1 1 0 0 0-1.74 0z';
                    default:
                        return 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z';
                }
            }
        }"
        class="pointer-events-none fixed top-5 right-5 z-50 flex max-w-sm flex-col gap-3"
    >
        <template x-for="(toast, index) in toasts" :key="index">
            <div
                x-data
                x-init="setTimeout(() => remove(index), toast.duration || 5000)"
                x-show="true"
                x-transition.opacity.duration.300ms
                :class="'pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg '+classes(toast.type)"
            >
                <div class="mt-0.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="icon(toast.type)"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold" x-text="toast.message"></p>
                    <template x-if="toast.description">
                        <p class="mt-1 text-xs" x-text="toast.description"></p>
                    </template>
                </div>
                <button
                    type="button"
                    class="rounded-full bg-black/5 px-2 py-1 text-xs font-semibold uppercase tracking-wide text-black/40 transition hover:bg-black/10 hover:text-black/60"
                    @click="remove(index)"
                >
                    {{ __('Close') }}
                </button>
            </div>
        </template>
    </div>
@endif
