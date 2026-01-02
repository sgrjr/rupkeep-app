@php
    use Illuminate\Support\Number;
    use Illuminate\Support\Str;

    $iconMap = [
        'Jobs' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="4" y1="3" x2="4" y2="21" stroke-linecap="round"/><path d="M4 3 L4 10 L14 6.5 L4 3 Z" fill="currentColor" stroke="none"/></svg>',
        'Users' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5C7.5 5.57 9.07 4 11 4C12.93 4 14.5 5.57 14.5 7.5C14.5 9.43 12.93 11 11 11C9.07 11 7.5 9.43 7.5 7.5ZM4 19C4 16.24 6.24 14 9 14H13C15.76 14 18 16.24 18 19M18.5 8.5H20M19.25 7.75V9.25"/></svg>',
        'Customers' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5C7.5 5.57 9.07 4 11 4C12.93 4 14.5 5.57 14.5 7.5C14.5 9.43 12.93 11 11 11C9.07 11 7.5 9.43 7.5 7.5ZM4 19C4 16.24 6.24 14 9 14H13C15.76 14 18 16.24 18 19M18.5 8.5H20M19.25 7.75V9.25"/></svg>',
        'Vehicles' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m-17.25 4.5h14.25m0 0h1.125c.621 0 1.125-.504 1.125-1.125V14.25m0 0c0 .621-.504 1.125-1.125 1.125H3.375c-.621 0-1.125-.504-1.125-1.125V8.25m12 6.75v-1.5m-6 1.5v-1.5m6 0v-1.5m-6 1.5H9m3 0H9m-1.5 0H9m1.5 0v-1.5m0 0H9m1.5 0H12m-1.5 0v-1.5m0 0H12m1.5 0H15m-1.5 0v-1.5m0 0H15m1.5 0H18m-1.5 0v-1.5m0 0H18m1.5 0H20.625c.621 0 1.125-.504 1.125-1.125V8.25c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v5.25c0 .621.504 1.125 1.125 1.125h1.5m13.5-3V6.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v8.25c0 .621.504 1.125 1.125 1.125h8.25c.621 0 1.125-.504 1.125-1.125V14.25z"/></svg>',
        'Organizations' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9L12 3L21 9V20H3V9ZM9 20V12H15V20"/></svg>',
        'Feedback' => '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>',
    ];
@endphp

<div id="dashboard" class="space-y-10 pb-8">
    <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-2xl sm:p-8">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-3">
                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    {{ $organization->name }}
                </h1>
                <p class="max-w-xl text-sm text-white/80">
                    {{ __('Stay in control - track jobs, manage your organization and customers, import data, all from one convenient dashboard.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
                <div class="rounded-2xl border border-white/30 bg-white/20 px-5 py-4 text-sm font-semibold shadow-xl backdrop-blur">
                    <p class="text-white/70 uppercase tracking-wider">{{ __('Today') }}</p>
                    <p class="text-lg">{{ now()->timezone(Auth::user()->timezone ?? config('app.timezone'))->isoFormat('MMM D, YYYY') }}</p>
                </div>
                <div class="rounded-2xl border border-white/30 bg-white/15 px-5 py-4 text-sm font-semibold shadow-xl backdrop-blur">
                    <p class="text-white/70 uppercase tracking-wider">{{ __('Active Roles') }}</p>
                    <p class="text-lg">{{ ucfirst(strtolower(Auth::user()->role_label)) }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="space-y-6 px-4 sm:px-6 lg:px-8">
        <div class="grid gap-4 overflow-x-auto pb-2 sm:grid-cols-2 xl:grid-cols-4 sm:gap-6" style="scroll-snap-type:x mandatory;">
            @foreach($cards as $card)
                @php
                    $icon = $iconMap[$card->title] ?? '<svg class="h-10 w-10 text-orange-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /></svg>';
                @endphp
                <div class="group relative min-w-[260px] overflow-hidden rounded-3xl border border-orange-100 bg-white/80 p-5 shadow-sm transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-xl sm:min-w-0 sm:p-6" style="scroll-snap-align:start;">
                    <div class="absolute right-0 top-0 h-32 w-32 -translate-y-14 translate-x-10 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-70"></div>
                    <div class="relative flex items-start justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $card->title }}</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($card->count) }}</p>
                        </div>
                        <span class="rounded-2xl bg-orange-50 p-2 text-orange-500">
                            @if($card->title === 'Jobs')
                                <x-icon-job-flag class="h-10 w-10 text-orange-500" />
                            @else
                                {!! $icon !!}
                            @endif
                        </span>
                    </div>

                    <div class="mt-5 space-y-3 text-sm">
                    @if($card->title === 'Jobs')
                            <div class="rounded-2xl bg-slate-50/80 px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Import from Spreadsheet') }}</p>
                                <div class="mt-3 space-y-3">
                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-dashed border-orange-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                                        <span class="truncate">
                                            @if($file)
                                                {{ Str::limit($file->getClientOriginalName(), 48) }}
                                            @else
                                                {{ __('Choose CSV file to import') }}
                                            @endif
                                        </span>
                                        <span class="inline-flex items-center rounded-full bg-orange-100 px-2 py-1 text-[10px] font-semibold text-orange-600">{{ __('Browse') }}</span>
                                        <input type="file" wire:model="file" class="hidden" accept=".csv,.xlsx" wire:change="$set('showPreview', false)">
                                    </label>
                                    @if($file)
                                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-400">
                                            {{ __('Selected') }}: {{ $file->getSize() ? Number::fileSize($file->getSize()) : $file->getMimeType() }}
                                        </p>
                                    @endif
                                    <div wire:loading.delay wire:target="confirmImport" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                        <div class="flex items-center gap-3">
                                            <svg class="h-5 w-5 animate-spin text-blue-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-semibold text-blue-900">{{ __('Importing...') }}</p>
                                                <p class="text-xs text-blue-700">{{ __('Please wait while we process your file. This may take a few moments.') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div wire:loading.remove.delay wire:target="confirmImport">
                                        @if ($errors->has('file'))
                                            <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                                                <p class="text-xs font-semibold text-red-700 whitespace-pre-line">{{ $errors->first('file') }}</p>
                                            </div>
                                        @elseif (session()->has('error'))
                                            <div class="rounded-lg border border-red-200 bg-red-50 p-3">
                                                <p class="text-xs font-semibold text-red-700 whitespace-pre-line">{{ session('error') }}</p>
                                            </div>
                                        @elseif (session()->has('success'))
                                            <p class="text-xs font-semibold text-emerald-600">{{ session('success') }}</p>
                                        @endif
                                    </div>
                                    
                                    @if(!$showPreview)
                                        <div class="flex items-center justify-between">
                                            <x-action-message class="text-xs font-medium text-emerald-600" on="uploaded">
                                        {{ __('File Uploaded.') }}
                                    </x-action-message>
                                            <button type="button" 
                                                    wire:click="previewHeaders"
                                                    @disabled(!$file)
                                                    class="inline-flex w-full items-center justify-center gap-1 rounded-full px-3 py-2 text-xs font-semibold text-white shadow-sm transition sm:w-auto {{ $file ? 'bg-orange-500 hover:bg-orange-600' : 'bg-slate-300 cursor-not-allowed' }}">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                {{ __('Preview Headers') }}
                                            </button>
                                        </div>
                                    @endif
                                    
                                    @if($showPreview && !empty($headerMappings))
                                        <div wire:loading.remove wire:target="confirmImport" class="rounded-lg border border-slate-200 bg-white p-4 space-y-3">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-700">{{ __('Header Mappings') }}</h4>
                                                <div class="flex items-center gap-3">
                                                    <span class="text-[10px] font-medium text-slate-500">{{ count($headerMappings) }} {{ __('columns') }}</span>
                                                    <span class="text-[10px] font-medium text-slate-600">{{ number_format($recordCount) }} {{ __('records') }}</span>
                                                </div>
                                            </div>
                                            <div class="max-h-64 overflow-y-auto space-y-1">
                                                <div class="grid grid-cols-12 gap-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500 border-b border-slate-200 pb-1">
                                                    <div class="col-span-1">#</div>
                                                    <div class="col-span-4">CSV Header</div>
                                                    <div class="col-span-3">Normalized</div>
                                                    <div class="col-span-4">Maps To</div>
                                                </div>
                                                @foreach($headerMappings as $mapping)
                                                    <div class="grid grid-cols-12 gap-2 text-[10px] {{ $mapping['status'] === 'unmapped' ? 'bg-red-50 text-red-700' : 'text-slate-600' }}">
                                                        <div class="col-span-1 font-medium">{{ $mapping['column'] }}</div>
                                                        <div class="col-span-4 truncate" title="{{ $mapping['original'] }}">{{ $mapping['original'] }}</div>
                                                        <div class="col-span-3 truncate text-slate-500">{{ $mapping['normalized'] }}</div>
                                                        <div class="col-span-4 font-medium {{ $mapping['status'] === 'mapped' ? 'text-emerald-600' : 'text-red-600' }}">
                                                            {{ $mapping['mapped_to'] }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200">
                                                <button type="button" 
                                                        wire:click="$set('showPreview', false)"
                                                        class="inline-flex items-center justify-center gap-1 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50">
                                                    {{ __('Cancel') }}
                                                </button>
                                                <form wire:submit.prevent="confirmImport">
                                                    <button type="submit" 
                                                            class="inline-flex items-center justify-center gap-1 rounded-full bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-600">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                                        {{ __('Confirm and Import') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                </div>
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

                    @if($card->title === 'Feedback' && $recentFeedback && $recentFeedback->count() > 0)
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Recent Submissions') }}</p>
                            <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                @foreach($recentFeedback as $feedback)
                                    <div class="rounded-xl border border-slate-100 bg-white px-3 py-2 shadow-sm">
                                        <div class="flex items-center justify-between gap-2 mb-1">
                                            <span class="text-xs font-semibold text-slate-800 truncate">{{ $feedback->user?->name ?? __('Anonymous') }}</span>
                                            <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide shrink-0 {{ $feedback->severity === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                                {{ ucfirst($feedback->severity) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-600 line-clamp-2 mt-1">{{ $feedback->context['feedback'] ?? __('No feedback text') }}</p>
                                        <span class="text-[10px] text-slate-400 mt-1 block">{{ $feedback->created_at->diffForHumans() }}</span>
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
        <section class="mx-4 sm:mx-6 lg:mx-8 rounded-3xl border border-slate-200 bg-white/80 p-6 shadow-sm">
            <div class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Update Deployment') }}</p>
                            <p class="text-sm text-slate-700">{{ __('Pull the latest code from GitHub onto this server.') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.tools.update_from_git') }}" class="flex items-center gap-2">
                            @csrf
                            <x-button type="submit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5.17 18.83A9 9 0 0 0 18.83 5.17M18 9V4h-5"/></svg>
                                {{ __('Pull Latest Code') }}
                            </x-button>
                        </form>
                    </div>
                    @if(session('git_output'))
                        <details class="mt-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                            <summary class="cursor-pointer text-xs font-semibold text-slate-600">{{ __('Show command output') }}</summary>
                            <div class="mt-2 space-y-2">
                                @foreach(session('git_output') as $entry)
                                    <div class="rounded-lg bg-white p-2 text-[11px] leading-snug">
                                        <p class="font-semibold text-slate-700">{{ $entry['command'] }} ({{ __('exit') }}: {{ $entry['exit_code'] }})</p>
                                        @if($entry['stdout'])
                                            <pre class="mt-1 whitespace-pre-wrap text-slate-600">{{ $entry['stdout'] }}</pre>
                                        @endif
                                        @if($entry['stderr'])
                                            <pre class="mt-1 whitespace-pre-wrap text-red-600">{{ $entry['stderr'] }}</pre>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Server Management') }}</p>
                            <p class="text-sm text-slate-700">{{ __('Execute git, npm, and artisan commands with terminal output.') }}</p>
                        </div>
                        <a href="{{ route('admin.server-management') }}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3V6a3 3 0 013-3h13.5a3 3 0 013 3v5.25a3 3 0 01-3 3m-16.5 0a3 3 0 00-3 3v2.25a3 3 0 003 3h13.5a3 3 0 003-3v-2.25a3 3 0 00-3-3m-16.5 0V9.75a3 3 0 013-3h13.5a3 3 0 013 3v4.5"/>
                            </svg>
                            {{ __('Open Server Management') }}
                        </a>
                    </div>
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
                        <p class="font-semibold">{{ __('Delete ALL Jobs + Logs + Invoices') }}</p>
                        <p class="mt-2 text-xs text-red-500">{{ __('This operation cannot be undone and will remove every job, log, and invoice across the platform.') }}</p>
                        <button class="mt-4 inline-flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white shadow-sm transition hover:bg-red-600"
                                wire:click="deleteJobs"
                                wire:confirm="Are you sure you want to Delete ALL Jobs, Logs, and Invoices? This is not reversible!">
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

        @if($jobsMarkedForAttention && $jobsMarkedForAttention->count() > 0 && auth()->user()->can('createJob', $organization))
        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm m-8">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Invoices Marked for Attention') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Jobs with invoices that require staff attention.') }}</p>
                </div>
            </header>

            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Job #') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Load #') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Pickup') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Delivery') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Invoice') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Attention') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($jobsMarkedForAttention as $job)
                            @php
                                $primaryInvoice = $job->invoices->whereNull('parent_invoice_id')->sortByDesc('created_at')->first();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}" class="text-orange-600 hover:text-orange-700">{{ $job->job_no ?? __('No #') }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $job->load_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ optional($job->scheduled_pickup_at)->format('M j, Y g:ia') ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $job->delivery_address ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    @if($job->invoice_paid)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">{{ __('Invoice Paid') }}</span>
                                    @elseif($primaryInvoice)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600">
                                                {{ $primaryInvoice->isSummary() ? __('Summary Invoice #:number', ['number' => $primaryInvoice->invoice_number]) : __('Invoice #:number', ['number' => $primaryInvoice->invoice_number]) }}
                                            </span>
                                            <a href="{{ route('my.invoices.edit', ['invoice' => $primaryInvoice->id]) }}" class="inline-flex items-center text-xs font-semibold text-orange-600 underline hover:text-orange-700">
                                                {{ __('View invoice') }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-500">{{ __('Ready for invoicing') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($primaryInvoice)
                                        <button type="button" onclick="(function() { const formData = new FormData(); formData.append('_token', document.querySelector('meta[name=csrf-token]').content); fetch('{{ route('my.invoices.toggle-marked-for-attention', ['invoice' => $primaryInvoice->id]) }}', { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData }).then(r => r.json()).then(data => { if(data.success) { window.location.reload(); } }); })()" class="inline-flex items-center gap-1 rounded-full {{ $primaryInvoice->marked_for_attention ? 'bg-red-100 text-red-700 border border-red-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }} px-3 py-1 text-xs font-semibold hover:opacity-80 transition">
                                            @if($primaryInvoice->marked_for_attention)
                                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ __('Marked') }}
                                            @else
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                                                </svg>
                                                {{ __('Mark') }}
                                            @endif
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        @if($managerStats)
        <section class="mx-4 sm:mx-6 lg:mx-8 space-y-6 rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                        <x-icon-job-flag class="h-6 w-6 text-orange-500" />
                        {{ __('Job Status Overview') }}
                    </h2>
                    <p class="text-sm text-slate-500">{{ __('At-a-glance view of all jobs and financial status.') }}</p>
                </div>
                <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                    {{ __('View All Jobs') }}
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5L15 12L9 19"/></svg>
                </a>
            </header>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Jobs') }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($managerStats->total_jobs) }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">{{ __('Active Jobs') }}</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($managerStats->active_jobs) }}</p>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600">{{ __('Completed') }}</p>
                    <p class="mt-2 text-3xl font-bold text-amber-700">{{ number_format($managerStats->completed_jobs) }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-600">{{ __('Cancelled') }}</p>
                    <p class="mt-2 text-3xl font-bold text-red-700">{{ number_format($managerStats->cancelled_jobs) }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Total Invoices') }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-900">{{ number_format($managerStats->total_invoices) }}</p>
                </div>
                <div class="rounded-2xl border border-orange-200 bg-orange-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-orange-600">{{ __('Unpaid Invoices') }}</p>
                    <p class="mt-2 text-3xl font-bold text-orange-700">{{ number_format($managerStats->unpaid_invoices) }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">{{ __('Total Revenue') }}</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-700">${{ number_format($managerStats->total_revenue, 2) }}</p>
                </div>
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-600">{{ __('Outstanding') }}</p>
                    <p class="mt-2 text-2xl font-bold text-red-700">${{ number_format($managerStats->unpaid_amount, 2) }}</p>
                </div>
                <a href="{{ route('my.customers.index', ['has_account_credit' => 1]) }}" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-600">{{ __('Sum of Account Credits') }}</p>
                    <p class="mt-2 text-2xl font-bold text-blue-700">${{ number_format($managerStats->total_account_credits, 2) }}</p>
                </a>
            </div>

            @if($recentJobs && $recentJobs->count() > 0)
            <div class="mt-6 space-y-3">
                <h3 class="text-lg font-semibold text-slate-900">{{ __('Recent Jobs') }}</h3>
                <div class="space-y-2">
                    @foreach($recentJobs as $job)
                        @php
                            $statusColor = match($job->status) {
                                'ACTIVE' => 'bg-emerald-100 text-emerald-700',
                                'COMPLETED' => 'bg-amber-100 text-amber-700',
                                'CANCELLED', 'CANCELLED_NO_GO' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        @endphp
                        <a href="{{ route('my.jobs.show', ['job' => $job->id]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-orange-200 hover:shadow-md">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900">{{ $job->job_no ?? __('Job') }} #{{ $job->id }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $statusColor }}">
                                        {{ $job->status_label }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-600">{{ $job->customer?->name ?? __('Unassigned Customer') }}</p>
                                @if($job->load_no)
                                    <p class="mt-1 text-xs text-slate-400">{{ __('Load') }}: {{ $job->load_no }}</p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-slate-500">{{ optional($job->created_at)->diffForHumans() }}</p>
                                @if($job->invoices->count() > 0)
                                    <p class="mt-1 text-xs font-semibold text-orange-600">{{ $job->invoices->count() }} {{ trans_choice('invoice|invoices', $job->invoices->count()) }}</p>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </section>
        @endif

        @if($jobs && count($jobs) > 0)
        <section class="space-y-4 rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm px-4 sm:px-6 lg:px-8">
            <header class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ __('My Assigned Jobs') }}</h2>
                    <p class="text-sm text-slate-500">{{ __('Quick access to your latest work logs and customer contacts.') }}</p>
                </div>
                <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
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