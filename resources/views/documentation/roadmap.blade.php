<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ __('Public Roadmap') }}</h2>
                <p class="text-xs text-slate-500">{{ __('What we are working on and shipping next.') }}</p>
            </div>
            <a href="{{ route('documentation.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-orange-600 hover:text-orange-700">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Documentation') }}
            </a>
        </div>
    </x-slot>

    @php
        $statusOrder = ['triage', 'in_progress', 'open', 'verifying', 'done', 'declined'];
        $statusLabels = [
            'triage' => __('Up Next'),
            'in_progress' => __('In Progress'),
            'open' => __('Planned'),
            'verifying' => __('In Review'),
            'done' => __('Recently Shipped'),
            'declined' => __('Not Doing'),
        ];
        $statusDescriptions = [
            'triage' => __('Recently submitted — being evaluated and prioritized.'),
            'in_progress' => __('Currently being worked on.'),
            'open' => __('Planned and queued for an upcoming work cycle.'),
            'verifying' => __('Implementation is complete and live — we are confirming it solved the underlying issue before marking it shipped.'),
            'done' => __('Shipped and confirmed working in production.'),
            'declined' => __('Reviewed and intentionally not being pursued.'),
        ];
        $statusStyles = [
            'triage' => 'border-amber-200 bg-amber-50',
            'in_progress' => 'border-blue-200 bg-blue-50',
            'open' => 'border-slate-200 bg-white',
            'verifying' => 'border-violet-200 bg-violet-50',
            'done' => 'border-emerald-200 bg-emerald-50',
            'declined' => 'border-slate-200 bg-slate-50',
        ];
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Overview banner --}}
            @if (!$tasksByStatus->isEmpty())
                <section class="rounded-3xl border border-orange-200 bg-gradient-to-br from-orange-50 via-white to-orange-50/40 p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-orange-600">{{ __('Overview') }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $totalPublic }} <span class="text-base font-medium text-slate-500">{{ __('open items on the roadmap') }}</span></p>
                        </div>
                        @if ($lastUpdatedAt)
                            <div class="text-right">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('Last updated') }}</p>
                                <p class="mt-0.5 text-sm font-semibold text-slate-800">{{ $lastUpdatedAt->format('g:i:s A') }} <span class="text-slate-500">{{ $lastUpdatedAt->format('n/j/Y') }}</span></p>
                                <p class="text-[11px] text-slate-400">{{ $lastUpdatedAt->diffForHumans() }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach ($statusOrder as $status)
                            @php $count = $tasksByStatus->get($status)?->count() ?? 0; @endphp
                            <a href="#section-{{ $status }}" @class([
                                'block rounded-2xl border px-3 py-2 text-center transition hover:shadow-sm',
                                $statusStyles[$status] ?? 'border-slate-200 bg-white',
                                'opacity-50' => $count === 0,
                            ])>
                                <p class="text-2xl font-bold text-slate-900">{{ $count }}</p>
                                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">{{ $statusLabels[$status] ?? $status }}</p>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($tasksByStatus->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white/60 p-12 text-center text-sm text-slate-500">
                    {{ __('No public roadmap items yet. Check back soon!') }}
                </div>
            @else
                @foreach ($statusOrder as $status)
                    @php $bucket = $tasksByStatus->get($status); @endphp
                    @if ($bucket && $bucket->isNotEmpty())
                        <section id="section-{{ $status }}" @class([
                            'rounded-3xl border p-5 shadow-sm sm:p-6 scroll-mt-6',
                            $statusStyles[$status] ?? 'border-slate-200 bg-white',
                        ])>
                            <header class="mb-4">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ $statusLabels[$status] ?? $status }} <span class="text-slate-400">· {{ $bucket->count() }}</span></h3>
                                @if (!empty($statusDescriptions[$status]))
                                    <p class="mt-1 text-xs text-slate-500">{{ $statusDescriptions[$status] }}</p>
                                @endif
                            </header>
                            <ul class="space-y-3">
                                @foreach ($bucket as $task)
                                    <li class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 shadow-sm">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-slate-900">{{ $task->title }}</p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] uppercase tracking-wide">
                                                    <span class="text-slate-400">{{ $task->code }}</span>
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">{{ $task->type }}</span>
                                                    @foreach ($task->labels as $label)
                                                        @if (!str_starts_with($label->name, 'epic:'))
                                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold text-white" style="background-color: {{ $label->color ?: '#94a3b8' }}">{{ $label->name }}</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            @if ($task->updated_at)
                                                <span class="whitespace-nowrap text-right text-[11px] text-slate-400" title="{{ $task->updated_at->toDateTimeString() }}">
                                                    {{ $task->updated_at->diffForHumans() }}
                                                    <span class="hidden sm:inline">·</span>
                                                    <span class="block sm:inline text-slate-500">{{ $task->updated_at->format('g:i:s A') }} {{ $task->updated_at->format('n/j/Y') }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endforeach
            @endif

            <p class="text-center text-xs text-slate-400">
                {{ __('See something missing? ') }}
                <a href="{{ route('documentation.index') }}" class="font-semibold text-orange-600 hover:text-orange-700">{{ __('Send feedback') }}</a>
                {{ __('and we will track it here.') }}
            </p>
        </div>
    </div>
</x-app-layout>
