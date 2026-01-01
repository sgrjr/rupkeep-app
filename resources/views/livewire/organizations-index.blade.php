<div>
    <div class="mx-auto space-y-8">
        <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Organization Management') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ __('Organizations') }}</h1>
                    <p class="text-sm text-white/85">
                        {{ __('Manage organizations and their settings.') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white/85 shadow-sm backdrop-blur">
                        {{ trans_choice('{0} No organizations|{1} :count organization|[2,*] :count organizations', $organizations->count(), ['count' => $organizations->count()]) }}
                    </span>
                    @can('create', \App\Models\Organization::class)
                        <a href="{{ route('organizations.create') }}"
                           class="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white/85 shadow-sm backdrop-blur transition hover:bg-white/20">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Create') }}
                        </a>
                    @endcan
                </div>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">

            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @forelse($organizations as $org)
                    <div class="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                        <div class="absolute right-0 top-0 h-24 w-24 -translate-y-12 translate-x-6 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-80"></div>

                        <div class="relative space-y-4">
                            <div>
                                <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('Organization') }}</p>
                                <h3 class="text-lg font-semibold text-slate-900">{{ $org->name }}</h3>
                            </div>

                            <div class="space-y-2 text-sm text-slate-600">
                                @if($org->primary_contact)
                                    <p>
                                        <span class="font-semibold text-slate-500">{{ __('Contact') }}:</span>
                                        {{ $org->primary_contact }}
                                    </p>
                                @endif
                                @if($org->telephone)
                                    <p>
                                        <span class="font-semibold text-slate-500">{{ __('Phone') }}:</span>
                                        <a href="tel:{{ $org->telephone }}" class="text-orange-600 hover:text-orange-700">{{ $org->telephone }}</a>
                                    </p>
                                @endif
                                @if($org->email)
                                    <p>
                                        <span class="font-semibold text-slate-500">{{ __('Email') }}:</span>
                                        <a href="mailto:{{ $org->email }}" class="text-orange-600 hover:text-orange-700">{{ $org->email }}</a>
                                    </p>
                                @endif
                                @if($org->street || $org->city || $org->state || $org->zip)
                                    <p class="text-xs text-slate-500">
                                        @if($org->street){{ $org->street }}@endif
                                        @if($org->city){{ $org->city }}, @endif
                                        @if($org->state){{ $org->state }} @endif
                                        @if($org->zip){{ $org->zip }}@endif
                                    </p>
                                @endif
                                @if($org->website_url)
                                    <p>
                                        <span class="font-semibold text-slate-500">{{ __('Website') }}:</span>
                                        <a href="{{ $org->website_url }}" target="_blank" rel="noopener noreferrer" class="text-orange-600 hover:text-orange-700">{{ parse_url($org->website_url, PHP_URL_HOST) ?? $org->website_url }}</a>
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                                <a href="{{ route('organizations.show', ['organization' => $org->id]) }}"
                                   class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ __('View') }}
                                </a>
                                @can('update', $org)
                                    <a href="{{ route('organizations.edit', ['organization' => $org->id]) }}"
                                       class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                        {{ __('Edit') }}
                                    </a>
                                @endcan
                                @can('delete', $org)
                                    <livewire:delete-confirmation-button
                                        :action-url="route('organizations.delete', $org->id)"
                                        button-text="{{ __('Delete') }}"
                                        button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700"
                                        :model-class="\App\Models\Organization::class"
                                        :record-id="$org->id"
                                        resource="organizations"
                                        redirect-route="organizations.index"
                                    />
                                @endcan
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-slate-50/80 py-12 text-center text-sm text-slate-400">
                        {{ __('No organizations yet.') }}
                    </div>
                @endforelse
            </div>
        </section>
        </div>
    </div>
</div>