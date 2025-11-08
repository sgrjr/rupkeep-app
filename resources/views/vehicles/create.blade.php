<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-orange-500">{{ __('Fleet Management') }}</p>
                <h1 class="text-3xl font-bold text-slate-900">{{ __('Add Vehicle') }}</h1>
                <p class="text-sm text-slate-500">{{ __('Capture baseline information so you can schedule assignments and track maintenance.') }}</p>
            </div>
            <a href="{{ route('my.vehicles.index') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-orange-200 hover:text-orange-600">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H5m7-7l-7 7 7 7"/></svg>
                {{ __('Back to vehicles') }}
            </a>
        </div>

        <form action="{{ route('my.vehicles.store') }}" method="post" class="space-y-6">
            @csrf

            <section class="rounded-3xl border border-slate-100 bg-white/90 p-6 shadow-sm">
                <header class="mb-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Vehicle Details') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Give each escort vehicle a name so drivers can select it when logging work.') }}</p>
                </header>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Vehicle Name') }}</label>
                        <input id="name" name="name" value="{{ old('name') }}" required placeholder="{{ __('E.g. Chevy Tahoe #2') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <x-input-error for="name" class="mt-2 text-xs font-semibold text-red-500" />
                    </div>

                    <div>
                        <label for="odometer" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Current Odometer (mi)') }}</label>
                        <input id="odometer" name="odometer" type="number" min="0" value="{{ old('odometer') }}" placeholder="0" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <x-input-error for="odometer" class="mt-2 text-xs font-semibold text-red-500" />
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <x-button>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Create Vehicle') }}
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>
