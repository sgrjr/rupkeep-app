@php
    use Illuminate\Support\Str;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Experience Tracker') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('User Events') }}</h1>
                <p class="text-xs text-slate-500">{{ __('Track errors, warnings, and user actions across the application.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <!-- Filters -->
        <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
            <form method="GET" action="{{ route('user-events.index') }}" class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Severity') }}</label>
                    <select name="severity" class="mt-2 block rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="">{{ __('All') }}</option>
                        <option value="error" {{ request('severity') === 'error' ? 'selected' : '' }}>{{ __('Error') }}</option>
                        <option value="warning" {{ request('severity') === 'warning' ? 'selected' : '' }}>{{ __('Warning') }}</option>
                        <option value="info" {{ request('severity') === 'info' ? 'selected' : '' }}>{{ __('Info') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Type') }}</label>
                    <select name="type" class="mt-2 block rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="">{{ __('All') }}</option>
                        <option value="error" {{ request('type') === 'error' ? 'selected' : '' }}>{{ __('Error') }}</option>
                        <option value="warning" {{ request('type') === 'warning' ? 'selected' : '' }}>{{ __('Warning') }}</option>
                        <option value="info" {{ request('type') === 'info' ? 'selected' : '' }}>{{ __('Info') }}</option>
                        <option value="action" {{ request('type') === 'action' ? 'selected' : '' }}>{{ __('Action') }}</option>
                        <option value="feedback" {{ request('type') === 'feedback' ? 'selected' : '' }}>{{ __('Feedback') }}</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                        {{ __('Filter') }}
                    </button>
                    @if(request()->hasAny(['severity', 'type', 'user_id']))
                        <a href="{{ route('user-events.index') }}" class="ml-2 text-sm text-slate-600 hover:text-orange-600">
                            {{ __('Clear') }}
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Events List -->
        <div class="space-y-3">
            @forelse($events as $event)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span @class([
                                    'inline-flex items-center gap-2 rounded-full px-2 py-1 text-xs font-semibold',
                                    'bg-red-100 text-red-700 border border-red-200' => $event->severity === 'error',
                                    'bg-amber-100 text-amber-700 border border-amber-200' => $event->severity === 'warning',
                                    'bg-blue-100 text-blue-700 border border-blue-200' => $event->severity === 'info',
                                ])>
                                    {{ ucfirst($event->severity) }}
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">
                                    {{ ucfirst($event->type) }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-slate-900">
                                @if($event->context && isset($event->context['message']))
                                    {{ Str::limit($event->context['message'], 100) }}
                                @elseif($event->context && isset($event->context['action']))
                                    {{ Str::limit($event->context['action'], 100) }}
                                @else
                                    {{ __('Event') }} #{{ $event->id }}
                                @endif
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                                @if($event->user)
                                    <span>{{ __('User') }}: {{ $event->user->name }} ({{ $event->user->email }})</span>
                                @else
                                    <span>{{ __('Guest User') }}</span>
                                @endif
                                @if($event->url)
                                    <span class="truncate max-w-xs">{{ __('URL') }}: {{ $event->url }}</span>
                                @endif
                                @if($event->ip)
                                    <span>{{ __('IP') }}: {{ $event->ip }}</span>
                                @endif
                                <span>{{ $event->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <a href="{{ route('user-events.show', $event) }}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                            {{ __('View Details') }}
                        </a>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400">
                    {{ __('No events found.') }}
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $events->links() }}
        </div>
    </div>
</x-app-layout>

