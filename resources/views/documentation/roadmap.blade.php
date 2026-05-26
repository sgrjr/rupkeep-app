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
        $statusOrder = ['in_progress', 'verifying', 'open', 'triage', 'done', 'declined'];
        $statusLabels = [
            'in_progress' => __('In Progress'),
            'verifying' => __('In Review'),
            'open' => __('Planned'),
            'triage' => __('Up Next'),
            'done' => __('Recently Shipped'),
            'declined' => __('Not Doing'),
        ];
        $statusStyles = [
            'in_progress' => 'border-blue-200 bg-blue-50',
            'verifying' => 'border-violet-200 bg-violet-50',
            'open' => 'border-slate-200 bg-white',
            'triage' => 'border-amber-200 bg-amber-50',
            'done' => 'border-emerald-200 bg-emerald-50',
            'declined' => 'border-slate-200 bg-slate-50',
        ];
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if ($tasksByStatus->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white/60 p-12 text-center text-sm text-slate-500">
                    {{ __('No public roadmap items yet. Check back soon!') }}
                </div>
            @else
                @foreach ($statusOrder as $status)
                    @php $bucket = $tasksByStatus->get($status); @endphp
                    @if ($bucket && $bucket->isNotEmpty())
                        <section @class([
                            'rounded-3xl border p-5 shadow-sm sm:p-6',
                            $statusStyles[$status] ?? 'border-slate-200 bg-white',
                        ])>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ $statusLabels[$status] ?? $status }} <span class="text-slate-400">· {{ $bucket->count() }}</span></h3>
                            <ul class="mt-4 space-y-3">
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
                                            <span class="text-[11px] text-slate-400">{{ $task->updated_at?->diffForHumans() }}</span>
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
