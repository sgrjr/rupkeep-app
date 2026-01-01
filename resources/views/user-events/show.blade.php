<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('user-events.index') }}" class="text-xs font-semibold text-slate-400 hover:text-orange-600">
                    {{ __('← Back to Events') }}
                </a>
                <h1 class="mt-1 text-xl font-semibold text-slate-900">{{ __('Event Details') }}</h1>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <div class="space-y-6">
                <!-- Header Info -->
                <div class="flex flex-wrap items-center gap-3">
                    <span @class([
                        'inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
                        'bg-red-100 text-red-700 border border-red-200' => $userEvent->severity === 'error',
                        'bg-amber-100 text-amber-700 border border-amber-200' => $userEvent->severity === 'warning',
                        'bg-blue-100 text-blue-700 border border-blue-200' => $userEvent->severity === 'info',
                    ])>
                        {{ ucfirst($userEvent->severity) }}
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ ucfirst($userEvent->type) }}
                    </span>
                    <span class="text-xs text-slate-500">{{ $userEvent->created_at->format('M j, Y g:ia') }}</span>
                </div>

                <!-- User Info -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('User') }}</p>
                        <p class="mt-1 text-sm text-slate-900">
                            @if($userEvent->user)
                                {{ $userEvent->user->name }} ({{ $userEvent->user->email }})
                            @else
                                {{ __('Guest User') }}
                            @endif
                        </p>
                    </div>
                    @if($userEvent->ip)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('IP Address') }}</p>
                            <p class="mt-1 text-sm text-slate-900">{{ $userEvent->ip }}</p>
                        </div>
                    @endif
                </div>

                <!-- URL -->
                @if($userEvent->url)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('URL') }}</p>
                        <p class="mt-1 break-all text-sm text-slate-900">{{ $userEvent->url }}</p>
                    </div>
                @endif

                <!-- Context -->
                @if($userEvent->context)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Context') }}</p>
                        <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <pre class="overflow-x-auto text-xs text-slate-700 whitespace-pre-wrap">{{ json_encode($userEvent->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

