@php
    use Illuminate\Support\Str;
@endphp

<style>
    .chart-bar-wrapper:hover .chart-bar {
        opacity: 0.9;
        transform: scaleY(1.05);
    }
    .chart-bar-wrapper:hover .chart-bar-value {
        opacity: 1 !important;
    }
    .chart-bar-wrapper:hover .chart-label {
        color: #f9b104;
        font-weight: 600;
    }
</style>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide">{{ __('Experience Tracker') }}</p>
                <h1 class="text-xl font-semibold">{{ __('User Events') }} <span class="text-base font-normal">({{ number_format($totalEvents ?? 0) }})</span></h1>
                <p class="text-xs">{{ __('Track errors, warnings, and user actions across the application.') }}</p>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <!-- Daily Event Count Chart -->
        @php
            $dailyEventCounts = $dailyEventCounts ?? [];
        @endphp
        @if(is_array($dailyEventCounts) && count($dailyEventCounts) > 0)
        <div class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Daily Event Count (Last 30 Days)') }}</h2>
                <p class="text-xs text-slate-500 mt-1">{{ __('Event frequency over time') }}</p>
            </div>
            
            @php
                $maxCount = !empty($dailyEventCounts) ? max($dailyEventCounts) : 1;
                if ($maxCount == 0) $maxCount = 1; // Prevent division by zero
                $chartHeight = 200;
            @endphp
            
            <div class="chart-container" style="position: relative; height: {{ $chartHeight }}px; margin-top: 2rem; padding-left: 2.5rem; overflow-x: auto;">
                <div class="chart-bars" style="display: flex; align-items: flex-end; justify-content: space-between; height: 100%; gap: 1px; padding: 0 0.5rem; min-width: 100%;">
                    @foreach($dailyEventCounts as $date => $count)
                        @php
                            $height = $maxCount > 0 ? ($count / $maxCount) * 100 : 0;
                            $dateObj = \Carbon\Carbon::parse($date);
                            $isToday = $dateObj->isToday();
                            $isWeekend = $dateObj->isWeekend();
                        @endphp
                        <div class="chart-bar-wrapper" style="flex: 1; min-width: 0; display: flex; flex-direction: column; align-items: center; height: 100%; position: relative;">
                            <div 
                                class="chart-bar {{ $isToday ? 'chart-bar-today' : '' }} {{ $isWeekend ? 'chart-bar-weekend' : '' }}"
                                style="
                                    width: 100%;
                                    max-width: 12px;
                                    height: {{ $height }}%;
                                    min-height: {{ $count > 0 ? '2px' : '0' }};
                                    background: {{ $isToday ? 'linear-gradient(to top, #f97316, #fb923c)' : ($isWeekend ? 'linear-gradient(to top, #cbd5e1, #e2e8f0)' : 'linear-gradient(to top, #f9b104, #fbbf24)') }};
                                    border-radius: 2px 2px 0 0;
                                    transition: all 0.2s ease;
                                    position: relative;
                                    cursor: pointer;
                                    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                                "
                                title="{{ $dateObj->format('M j, Y') }}: {{ $count }} {{ trans_choice('event|events', $count) }}"
                            >
                                @if($count > 0)
                                    <span class="chart-bar-value" style="
                                        position: absolute;
                                        top: -1.5rem;
                                        left: 50%;
                                        transform: translateX(-50%);
                                        font-size: 0.65rem;
                                        font-weight: 600;
                                        color: #475569;
                                        white-space: nowrap;
                                        opacity: 0;
                                        transition: opacity 0.2s;
                                        z-index: 10;
                                        background: white;
                                        padding: 0.125rem 0.25rem;
                                        border-radius: 0.25rem;
                                        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                    ">{{ $count }}</span>
                                @endif
                            </div>
                            <div class="chart-label" style="
                                margin-top: 0.5rem;
                                font-size: 0.6rem;
                                color: #64748b;
                                writing-mode: vertical-rl;
                                text-orientation: mixed;
                                transform: rotate(180deg);
                                white-space: nowrap;
                                height: 3rem;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                max-width: 100%;
                                overflow: hidden;
                            ">
                                {{ $dateObj->format('M j') }}
                            </div>
                        </div>
                    @endforeach
                </div>
                
                <!-- Y-axis labels -->
                <div class="chart-y-axis" style="
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 2.5rem;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    padding: 0.5rem 0;
                    pointer-events: none;
                ">
                    <span style="font-size: 0.65rem; color: #94a3b8; text-align: right; padding-right: 0.5rem;">{{ $maxCount }}</span>
                    <span style="font-size: 0.65rem; color: #94a3b8; text-align: right; padding-right: 0.5rem;">{{ round($maxCount * 0.75) }}</span>
                    <span style="font-size: 0.65rem; color: #94a3b8; text-align: right; padding-right: 0.5rem;">{{ round($maxCount * 0.5) }}</span>
                    <span style="font-size: 0.65rem; color: #94a3b8; text-align: right; padding-right: 0.5rem;">{{ round($maxCount * 0.25) }}</span>
                    <span style="font-size: 0.65rem; color: #94a3b8; text-align: right; padding-right: 0.5rem;">0</span>
                </div>
            </div>
            
            <!-- Legend -->
            <div class="chart-legend" style="
                display: flex;
                gap: 1.5rem;
                margin-top: 1.5rem;
                padding-top: 1rem;
                border-top: 1px solid #e2e8f0;
                flex-wrap: wrap;
            ">
                <div class="legend-item" style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 1rem; height: 1rem; background: linear-gradient(to top, #f9b104, #fbbf24); border-radius: 2px;"></div>
                    <span style="font-size: 0.75rem; color: #64748b;">{{ __('Weekday') }}</span>
                </div>
                <div class="legend-item" style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 1rem; height: 1rem; background: linear-gradient(to top, #cbd5e1, #e2e8f0); border-radius: 2px;"></div>
                    <span style="font-size: 0.75rem; color: #64748b;">{{ __('Weekend') }}</span>
                </div>
                <div class="legend-item" style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 1rem; height: 1rem; background: linear-gradient(to top, #f97316, #fb923c); border-radius: 2px;"></div>
                    <span style="font-size: 0.75rem; color: #64748b;">{{ __('Today') }}</span>
                </div>
            </div>
        </div>
        @endif

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

        <!-- Prune Old Events -->
        <div class="rounded-3xl border border-red-200 bg-red-50/60 p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ __('Prune Old Events') }}</h3>
                    <p class="text-sm text-slate-600 mt-1">{{ __('Delete user events older than the specified number of days. This action cannot be undone.') }}</p>
                </div>
                <form method="POST" action="{{ route('user-events.prune') }}" class="flex items-end gap-3" onsubmit="return confirm('{{ __('Are you sure you want to delete old user events? This action cannot be undone.') }}');">
                    @csrf
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-2">{{ __('Keep Last (Days)') }}</label>
                        <select name="keep_days" class="block rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" required>
                            <option value="30" selected>30 {{ __('days') }}</option>
                            <option value="60">60 {{ __('days') }}</option>
                            <option value="90">90 {{ __('days') }}</option>
                            <option value="180">180 {{ __('days') }}</option>
                            <option value="365">365 {{ __('days') }}</option>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-red-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        {{ __('Prune Events') }}
                    </button>
                </form>
            </div>
            <div class="mt-4 rounded-xl border border-red-200 bg-white px-4 py-3">
                <p class="text-xs text-slate-600">
                    <strong>{{ __('Note:') }}</strong> {{ __('This will permanently delete all user events older than the selected number of days. The daily event count chart cache will be automatically cleared.') }}
                </p>
            </div>
            
            <!-- Clear All Events -->
            <div class="mt-4 border-t border-red-300 pt-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h4 class="text-sm font-semibold text-red-900">{{ __('Clear All Events') }}</h4>
                        <p class="text-xs text-red-700 mt-1">{{ __('Permanently delete ALL user events. This action cannot be undone.') }}</p>
                    </div>
                    <form method="POST" action="{{ route('user-events.clear-all') }}" onsubmit="return confirm('{{ __('WARNING: This will delete ALL user events permanently. This action cannot be undone. Are you absolutely sure?') }}');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-red-700 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-red-800">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                            </svg>
                            {{ __('Clear All Events') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

