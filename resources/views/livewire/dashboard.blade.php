@php
    $iconMap = [
        'Jobs' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25V14.25C9 13.56 9.56 13 10.25 13H13.75C14.44 13 15 13.56 15 14.25V17.25M6.75 21H17.25C18.49 21 19.5 19.99 19.5 18.75V9.75C19.5 9.06 18.94 8.5 18.25 8.5H5.75C5.06 8.5 4.5 9.06 4.5 9.75V18.75C4.5 19.99 5.51 21 6.75 21ZM8.25 8.5V5.75C8.25 4.51 9.26 3.5 10.5 3.5H13.5C14.74 3.5 15.75 4.51 15.75 5.75V8.5" /></svg>',
        'Users' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128C13.875 20.321 12.314 21 10.5 21C6.91 21 4 18.09 4 14.5C4 10.91 6.91 8 10.5 8C12.314 8 13.875 8.679 15 9.872M19 8V12M21 10H17"/></svg>',
        'Customers' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5C7.5 5.57 9.07 4 11 4C12.93 4 14.5 5.57 14.5 7.5C14.5 9.43 12.93 11 11 11C9.07 11 7.5 9.43 7.5 7.5ZM4 19C4 16.24 6.24 14 9 14H13C15.76 14 18 16.24 18 19M18.5 8.5H20M19.25 7.75V9.25"/></svg>',
        'Organizations' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9L12 3L21 9V20H3V9ZM9 20V12H15V20"/></svg>',
    ];
@endphp

<div id="dashboard" class="space-y-10">
    <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-8 text-white shadow-2xl">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
        <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div class="space-y-3">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                    <span class="inline-block h-2 w-2 rounded-full bg-lime-300 animate-pulse"></span>
                    {{ __('Live Organization Workspace') }}
                </span>
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    {{ $organization->name }}
                </h1>
                <p class="max-w-xl text-sm text-white/80">
                    {{ __('Monitor jobs, team activity, and customer relationships from one place. Kick off imports, invite collaborators, and stay ahead of upcoming work.') }}
                </p>
            </div>
            <div class="flex items-center justify-end gap-6">
                <div class="rounded-2xl border border-white/30 bg-white/20 px-5 py-4 text-sm font-semibold shadow-xl backdrop-blur">
                    <p class="text-white/70 uppercase tracking-wider">{{ __('Today') }}</p>
                    <p class="text-lg">{{ now()->timezone(Auth::user()->timezone ?? config('app.timezone'))->isoFormat('MMM D, YYYY') }}</p>
                </div>
                <div class="hidden sm:block rounded-2xl border border-white/30 bg-white/15 px-5 py-4 text-sm font-semibold shadow-xl backdrop-blur">
                    <p class="text-white/70 uppercase tracking-wider">{{ __('Active Roles') }}</p>
                    <p class="text-lg">{{ ucfirst(strtolower(Auth::user()->role_label)) }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-6">
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                @php
                    $icon = $iconMap[$card->title] ?? '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /></svg>';
                @endphp
                <div class="group relative overflow-hidden rounded-3xl border border-orange-100 bg-white/80 p-6 shadow-sm transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-xl">
                    <div class="absolute right-0 top-0 h-32 w-32 -translate-y-14 translate-x-10 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-70"></div>
                    <div class="relative flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $card->title }}</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($card->count) }}</p>
                        </div>
                        <span class="rounded-2xl bg-orange-50 p-2 text-orange-500">
                            {!! $icon !!}
                        </span>
                    </div>

                    <div class="mt-5 space-y-3 text-sm">
                        @if($card->title === 'Jobs')
                            <div class="rounded-2xl bg-slate-50/80 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Import from Spreadsheet') }}</p>
                                <form class="mt-3 space-y-3" wire:submit.prevent="uploadFile">
                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-dashed border-orange-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                                        <span class="truncate">{{ __('Choose CSV file to import') }}</span>
                                        <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-1 text-[10px] font-semibold text-orange-600">{{ __('Browse') }}</span>
                                        <input type="file" wire:model="file" class="hidden" accept=".csv,.xlsx">
                                    </label>
                                    @error('file')
                                        <p class="text-xs font-semibold text-red-500">{{ $message }}</p>
                                    @enderror
                                    <div class="flex items-center justify-between">
                                        <x-action-message class="text-xs font-medium text-emerald-600" on="uploaded">
                                            {{ __('File Uploaded.') }}
                                        </x-action-message>
                                        <button type="submit" class="inline-flex items-center gap-1 rounded-full bg-orange-500 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16V14C4 10.13 7.13 7 11 7C14.87 7 18 10.13 18 14V16M12 3V11M12 11L9.5 8.5M12 11L14.5 8.5"/></svg>
                                            {{ __('Upload') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        @if($card->title === 'Organizations' && $organizations)
                            <div class="space-y-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Accounts Overview') }}</p>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    @foreach($organizations as $org)
                                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2 shadow-sm">
                                            <div class="flex items-center justify-between text-sm">
                                                <a href="{{ route('organizations.show', ['organization'=> $org->id]) }}" class="font-semibold text-slate-800 hover:text-orange-600">
                                                    {{ $org->name }}
                                                </a>
                                                <span class="text-xs text-slate-400">{{ $org->users->count() }} {{ __('users') }}</span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                                @foreach($org->users as $user)
                                                    <div class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-2 py-1">
                                                        <span class="font-medium text-slate-600">{{ Str::limit($user->name, 12) }}</span>
                                                        <span class="flex items-center gap-1">
                                                            <a class="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-semibold text-orange-600 shadow-sm hover:bg-orange-50" href="{{ route('user.profile',['user'=> $user->id]) }}">{{ __('Profile') }}</a>
                                                            <a class="rounded-full bg-orange-500 px-1.5 py-0.5 text-[10px] font-semibold text-white shadow-sm hover:bg-orange-600" href="{{ route('impersonate',['user'=> $user->id]) }}">{{ __('Impersonate') }}</a>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="relative mt-6 flex flex-wrap gap-2">
                        @foreach($card->links as $link)
                            <a class="inline-flex items-center gap-1 rounded-full border border-orange-200 bg-white/60 px-3 py-1 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white" href="{{ $link['url'] }}">
                                {{ $link['title'] }}
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5L15 12L9 19"/></svg>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if(auth()->user()->is_super)
        <section class="rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Update Deployment') }}</p>
                            <p class="text-sm text-slate-700">{{ __('Pull the latest code from GitHub onto this server.') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.git-update') }}" class="flex items-center gap-2">
                            @csrf
                            <x-button type="submit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.17 18.83A9 9 0 0 0 18.83 5.17M18 9V4h-5"/></svg>
                                {{ __('Pull Latest Code') }}
                            </x-button>
                        </form>
                    </div>
                    @if(session('git-update-status'))
                        <div class="mt-3 rounded-xl border {{ session('git-update-status') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }} px-3 py-2 text-xs">
                            <p class="font-semibold">
                                {{ session('git-update-status') === 'success' ? __('Update completed successfully.') : __('Update failed. See output below.') }}
                            </p>
                            @if(session('git-update-output'))
                                <pre class="mt-2 max-h-48 overflow-y-auto whitespace-pre-wrap rounded-lg bg-black/80 px-3 py-2 text-[11px] text-emerald-100">{{ session('git-update-output') }}</pre>
                            @endif
                        </div>
                    @endif
                </div>

                <details class="group space-y-4 rounded-2xl border border-red-100 bg-red-50/60 p-5 text-sm text-red-700">
                    <summary class="flex cursor-pointer items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-500 shadow-inner">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9V13M12 17H12.01M5.62 19H18.38C19.78 19 20.72 17.54 20.24 16.24L13.86 1.86C13.38 0.56 11.62 0.56 11.14 1.86L4.76 16.24C4.28 17.54 5.22 19 6.62 19Z"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-700">{{ __('Super Admin Tools') }}</p>
                                <p class="text-xs text-slate-500">{{ __('Danger zone: irreversible cleanup actions') }}</p>
                            </div>
                        </div>
                        <span class="rounded-full border border-red-100 bg-red-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-red-500 transition group-open:rotate-180">
                            {{ __('Expand') }}
                        </span>
                    </summary>

                    <div>
                        <p class="font-semibold">{{ __('Delete ALL Jobs + Logs') }}</p>
                        <p class="mt-2 text-xs text-red-500">{{ __('This operation cannot be undone and will remove every job and associated log across the platform.') }}</p>
                        <button class="mt-4 inline-flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-red-600"
                                wire:click="deleteJobs"
                                wire:confirm="Are you sure you want to Delete ALL Jobs and Logs? This is not reversible!">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/>
                            </svg>
                            {{ __('Execute Nuclear Cleanup') }}
                        </button>
                    </div>
                </details>
            </div>
        </section>
    @endif

    @if($jobs && count($jobs) > 0)
        <section class="space-y-4 rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ __('My Assigned Jobs') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('Quick access to your latest work logs and customer contacts.') }}</p>
                </div>
                <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                    {{ __('View All Jobs') }}
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5L15 12L9 19"/></svg>
                </a>
            </header>

            <div class="space-y-4">
                @foreach($jobs as $job)
                    <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:border-orange-100 hover:shadow-md">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-wider text-slate-400">{{ __('Job') }} #{{ $job->job_no }}</p>
                                <a href="{{ route('jobs.show', ['job'=> $job->id]) }}" class="text-lg font-semibold text-slate-900 hover:text-orange-600">
                                    {{ $job->customer?->name ?? __('Unassigned Customer') }}
                                </a>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {{ trans_choice('{0} No logs yet|{1} :count log|[2,*] :count logs', $job->logs->count(), ['count' => $job->logs->count()]) }}
                            </span>
                        </div>
                        @if($job->logs->count())
                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                @foreach($job->logs as $log)
                                    <div class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3">
                                        <div class="mt-1 h-2 w-2 rounded-full bg-orange-400"></div>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a class="inline-flex items-center gap-1 text-sm font-semibold text-orange-600 hover:text-orange-700" href="{{ route('logs.edit', ['log'=> $log->id]) }}">
                                                    {{ __('Edit Log') }}
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4L12 20M12 4L8 8M12 4L16 8"/></svg>
                                                </a>
                                                <span class="text-xs text-slate-500">{{ optional($log->created_at)->diffForHumans() }}</span>
                                            </div>
                                            <p class="text-xs text-slate-600">{{ $log->memo ?: __('No memo recorded yet.') }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>