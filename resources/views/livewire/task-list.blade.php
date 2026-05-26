<div class="space-y-6">
    {{-- Filter bar --}}
    <section class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Search') }}</label>
                <input type="text" wire:model.live.debounce.250ms="search" placeholder="{{ __('Search by title or code…') }}"
                    class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Status') }}</label>
                <select wire:model.live="statusFilter" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}">{{ str_replace('_',' ', $s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                <select wire:model.live="typeFilter" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any type') }}</option>
                    @foreach ($types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Priority') }}</label>
                <select wire:model.live="priorityFilter" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any priority') }}</option>
                    @foreach ($priorities as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Label') }}</label>
                <select wire:model.live="labelFilter" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any label') }}</option>
                    @foreach ($labels as $l)
                        <option value="{{ $l->name }}">{{ $l->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 text-xs text-slate-500">
                <span>{{ __('Sort:') }}</span>
                <select wire:model.live="sort" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="priority">{{ __('Priority') }}</option>
                    <option value="status">{{ __('Status') }}</option>
                    <option value="newest">{{ __('Newest') }}</option>
                    <option value="oldest">{{ __('Oldest') }}</option>
                    <option value="code">{{ __('Code (TASK-###)') }}</option>
                    <option value="title">{{ __('Title') }}</option>
                </select>
                @unless ($portal)
                    <span class="hidden sm:inline-block">·</span>
                    <select wire:model.live="publicFilter" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="">{{ __('Public + private') }}</option>
                        <option value="1">{{ __('Public only') }}</option>
                        <option value="0">{{ __('Private only') }}</option>
                    </select>
                @endunless
            </div>
            <button type="button" wire:click="clearFilters" class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                {{ __('Clear filters') }}
            </button>
        </div>
    </section>

    {{-- Results --}}
    <section class="rounded-3xl border border-slate-200 bg-white/90 shadow-sm">
        @if ($tasks->isEmpty())
            <div class="p-8 text-center text-sm text-slate-500">{{ __('No tasks match these filters.') }}</div>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($tasks as $task)
                    <li class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 transition hover:bg-slate-50/60">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ $portal ? route('portal.tasks.show', $task) : route('tasks.show', $task) }}" class="text-sm font-semibold text-orange-600 hover:text-orange-700">{{ $task->code }}</a>
                                <span class="text-sm text-slate-900">{{ $task->title }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] uppercase tracking-wide text-slate-500">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                                    'bg-red-50 text-red-600' => $task->priority === 'blocker',
                                    'bg-orange-50 text-orange-600' => $task->priority === 'high',
                                    'bg-amber-50 text-amber-600' => $task->priority === 'medium',
                                    'bg-emerald-50 text-emerald-600' => $task->priority === 'low',
                                ])>
                                    {{ $task->priority }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">{{ $task->type }}</span>
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold',
                                    'bg-blue-50 text-blue-600' => in_array($task->status, ['in_progress', 'verifying']),
                                    'bg-slate-100 text-slate-600' => in_array($task->status, ['triage', 'open']),
                                    'bg-emerald-50 text-emerald-600' => $task->status === 'done',
                                    'bg-rose-50 text-rose-600' => $task->status === 'declined',
                                ])>
                                    {{ str_replace('_',' ', $task->status) }}
                                </span>
                                @if ($task->is_public)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-600" title="{{ __('Visible to customer') }}">{{ __('public') }}</span>
                                @endif
                                @foreach ($task->labels as $label)
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold text-white" style="background-color: {{ $label->color ?: '#94a3b8' }}">{{ $label->name }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-right text-[11px] text-slate-500">
                            @if ($task->assignee)
                                <p>{{ __('Assigned: :name', ['name' => $task->assignee->name]) }}</p>
                            @endif
                            <p>{{ $task->updated_at?->diffForHumans() }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-5 py-3">{{ $tasks->links() }}</div>
        @endif
    </section>
</div>
