<div
    x-data="taskBoard()"
    x-init="init()"
    wire:ignore.self
    class="space-y-4"
>
    {{-- Filter bar --}}
    <section class="rounded-3xl border border-slate-200 bg-white/90 p-3 shadow-sm sm:p-4">
        <div class="flex flex-wrap items-center gap-3 text-xs">
            <label class="font-semibold uppercase tracking-wide text-slate-600">{{ __('Type') }}
                <select wire:model.live="typeFilter" class="ml-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($types as $t) <option value="{{ $t }}">{{ $t }}</option> @endforeach
                </select>
            </label>
            <label class="font-semibold uppercase tracking-wide text-slate-600">{{ __('Priority') }}
                <select wire:model.live="priorityFilter" class="ml-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($priorities as $p) <option value="{{ $p }}">{{ $p }}</option> @endforeach
                </select>
            </label>
            <label class="font-semibold uppercase tracking-wide text-slate-600">{{ __('Label') }}
                <select wire:model.live="labelFilter" class="ml-1 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <option value="">{{ __('Any') }}</option>
                    @foreach ($labels as $l) <option value="{{ $l->name }}">{{ $l->name }}</option> @endforeach
                </select>
            </label>
        </div>
    </section>

    {{-- Board --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @php
            $colLabels = [
                'triage' => __('Triage'),
                'open' => __('Open'),
                'in_progress' => __('In Progress'),
                'verifying' => __('Verifying'),
                'done' => __('Done'),
            ];
            $colColors = [
                'triage' => 'bg-amber-50 border-amber-200',
                'open' => 'bg-white border-slate-200',
                'in_progress' => 'bg-blue-50 border-blue-200',
                'verifying' => 'bg-violet-50 border-violet-200',
                'done' => 'bg-emerald-50 border-emerald-200',
            ];
        @endphp

        @foreach ($columns as $col)
            @php $cards = $byStatus->get($col, collect()); @endphp
            <section @class([
                'flex flex-col rounded-3xl border p-3 shadow-sm min-h-[200px]',
                $colColors[$col] ?? 'bg-white border-slate-200',
            ])>
                <header class="mb-2 flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <span>{{ $colLabels[$col] ?? $col }}</span>
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-slate-500">{{ $cards->count() }}</span>
                </header>
                <ul
                    class="space-y-2 flex-1"
                    data-status="{{ $col }}"
                    x-ref="col_{{ $col }}"
                >
                    @foreach ($cards as $task)
                        <li
                            data-code="{{ $task->code }}"
                            class="group cursor-grab rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs shadow-sm transition hover:shadow-md active:cursor-grabbing"
                        >
                            <div class="flex items-center justify-between">
                                <a href="{{ route('tasks.show', $task) }}" class="font-semibold text-orange-600 hover:text-orange-700">{{ $task->code }}</a>
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold uppercase',
                                    'bg-red-50 text-red-600' => $task->priority === 'blocker',
                                    'bg-orange-50 text-orange-600' => $task->priority === 'high',
                                    'bg-amber-50 text-amber-600' => $task->priority === 'medium',
                                    'bg-emerald-50 text-emerald-600' => $task->priority === 'low',
                                ])>{{ $task->priority }}</span>
                            </div>
                            <p class="mt-1 text-slate-800">{{ \Illuminate\Support\Str::limit($task->title, 90) }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1 text-[10px] uppercase tracking-wide">
                                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">{{ $task->type }}</span>
                                @if ($task->is_public)
                                    <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 font-semibold text-emerald-700">pub</span>
                                @endif
                                @if ($task->assignee)
                                    <span class="text-slate-500">{{ $task->assignee->name }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
</div>

@script
<script>
    if (!window.__sortableLoaded) {
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
        s.async = true;
        s.onload = () => { window.__sortableLoaded = true; window.dispatchEvent(new Event('sortable-loaded')); };
        document.head.appendChild(s);
    }

    window.taskBoard = () => ({
        init() {
            const wire = this.$wire;
            const bind = () => {
                document.querySelectorAll('[data-status]').forEach(col => {
                    if (col.__sortable) return;
                    col.__sortable = new Sortable(col, {
                        group: 'tasks',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: (evt) => {
                            const card = evt.item;
                            const code = card.getAttribute('data-code');
                            const toStatus = evt.to.getAttribute('data-status');
                            const orderedCodes = Array.from(evt.to.querySelectorAll('[data-code]')).map(el => el.getAttribute('data-code'));
                            wire.call('moveCard', code, toStatus, orderedCodes);
                        }
                    });
                });
            };
            if (window.__sortableLoaded) {
                bind();
            } else {
                window.addEventListener('sortable-loaded', bind, { once: true });
            }

            // After Livewire re-renders, re-bind on new column DOM nodes
            Livewire.hook('morph.added', () => {
                document.querySelectorAll('[data-status]').forEach(col => { col.__sortable = null; });
                if (window.__sortableLoaded) bind();
            });
        }
    });
</script>
@endscript
