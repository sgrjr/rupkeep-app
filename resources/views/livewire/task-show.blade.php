<div class="space-y-6">
    {{-- Header --}}
    <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-orange-600">{{ $task->code }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $task->title }}</h1>
                <div class="mt-3 flex flex-wrap items-center gap-1.5 text-[11px] uppercase tracking-wide">
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
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-600">public</span>
                    @endif
                    @foreach ($task->labels as $label)
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold text-white" style="background-color: {{ $label->color ?: '#94a3b8' }}">{{ $label->name }}</span>
                    @endforeach
                </div>
            </div>

            <div class="text-right text-xs text-slate-500">
                <p>{{ __('Submitted by') }}: <span class="font-semibold text-slate-700">{{ $task->submitter?->name ?? '—' }}</span></p>
                <p>{{ __('Assignee') }}: <span class="font-semibold text-slate-700">{{ $task->assignee?->name ?? '—' }}</span></p>
                <p class="mt-1">{{ $task->updated_at?->diffForHumans() }}</p>
            </div>
        </div>

        @if ($task->description)
            <div class="prose prose-sm mt-5 max-w-none rounded-2xl border border-slate-200 bg-slate-50 p-4 text-slate-700">
                {!! \Illuminate\Support\Str::markdown($task->description) !!}
            </div>
        @endif
    </section>

    {{-- Meta editor (staff only) --}}
    @if ($this->canEdit() && !$portal)
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-5 shadow-sm sm:p-6">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-600">{{ __('Task Properties') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Status') }}</label>
                    <select wire:model="status" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        @foreach ($statuses as $s) <option value="{{ $s }}">{{ str_replace('_',' ',$s) }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}</label>
                    <select wire:model="type" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Priority') }}</label>
                    <select wire:model="priority" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        @foreach ($priorities as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Assignee') }}</label>
                    <select wire:model="assignee_user_id" class="mt-1 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($assigneeOptions as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2">
                    <input type="checkbox" id="is_public" wire:model="is_public" class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                    <label for="is_public" class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-slate-700">{{ __('Visible to customer') }}</label>
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Labels') }}</label>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($allLabels as $label)
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold transition hover:border-orange-300 hover:bg-orange-50">
                            <input type="checkbox" value="{{ $label->id }}" wire:model="label_ids" class="h-3 w-3 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-white" style="background-color: {{ $label->color ?: '#94a3b8' }}">{{ $label->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <x-action-message class="text-xs font-semibold text-emerald-600" on="task-saved">{{ __('Saved.') }}</x-action-message>
                <button type="button" wire:click="saveMeta" class="inline-flex items-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-orange-600">
                    <span wire:loading wire:target="saveMeta" class="h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-t-transparent"></span>
                    {{ __('Save properties') }}
                </button>
            </div>
        </section>
    @endif

    {{-- Comment thread --}}
    <livewire:task-thread :task="$task" :portal="$portal" :key="'task-thread-'.$task->id"/>
</div>
