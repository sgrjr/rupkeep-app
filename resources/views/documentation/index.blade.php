<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-white">{{ __('Documentation') }}</h2>
                <p class="text-sm text-white/85">{{ __('Application reference and user guides') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="mb-6">
                <h1 class="text-3xl font-bold text-slate-900">{{ __('Documentation Center') }}</h1>
                <p class="mt-2 text-slate-600">{{ __('Browse available documentation and guides') }}</p>
            </header>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <a href="{{ route('documentation.show', 'onboarding') }}" 
                   class="group relative overflow-hidden rounded-2xl border border-orange-200 bg-gradient-to-br from-orange-50 to-orange-100/50 p-6 shadow-sm transition hover:border-orange-300 hover:shadow-lg">
                    <div class="absolute right-0 top-0 h-24 w-24 -translate-y-12 translate-x-6 rounded-full bg-orange-200/40 opacity-60 blur-3xl transition group-hover:opacity-80"></div>
                    <div class="relative">
                        <div class="mb-4 inline-flex rounded-xl bg-orange-500 p-3 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('Getting Started Guide') }}</h3>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Complete onboarding guide covering customer concerns, data import/export, features, and job lifecycle.') }}</p>
                        <div class="mt-4 flex items-center gap-2 text-sm font-semibold text-orange-600">
                            <span>{{ __('Read Guide') }}</span>
                            <svg class="h-4 w-4 transition group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
