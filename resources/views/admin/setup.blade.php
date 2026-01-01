@extends('layouts.guest')

@section('content')
    <div class="min-h-screen bg-slate-100/80 py-12">
        <div class="mx-auto max-w-xl px-4">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 px-6 py-8 text-white">
                    <h1 class="text-2xl font-bold">{{ __('Setup Console') }}</h1>
                    <p class="mt-2 text-sm text-white/85">
                        {{ __('Run post-deploy tasks such as database resets. Protected by environment credentials.') }}
                    </p>
                </div>

                <div class="space-y-6 px-6 py-6">
                    @if(session('success'))
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if(! $authorized)
                        <form method="POST" action="{{ route('setup.login') }}" class="space-y-5">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="username">
                                    {{ __('Username') }}
                                </label>
                                <input id="username" name="username" type="text" value="{{ old('username', config('setup-console.username')) }}" required autofocus
                                       class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="password">
                                    {{ __('Password') }}
                                </label>
                                <input id="password" name="password" type="password" required
                                       class="mt-2 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                @error('password')
                                    <p class="mt-2 text-xs font-semibold text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-orange-600">
                                {{ __('Unlock Console') }}
                            </button>
                        </form>
                    @else
                        <div class="space-y-4">
                            <form method="POST" action="{{ route('setup.run') }}" class="space-y-4">
                                @csrf
                                <input type="hidden" name="action" value="db-reset">

                                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                    <p class="font-semibold">{{ __('Reset the database') }}</p>
                                    <p class="mt-1 text-xs">
                                        {{ __('Runs migrate:fresh, seeds default data, and recreates the super user. Intended for fresh installs only.') }}
                                    </p>
                                </div>

                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-red-500 px-4 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-red-600"
                                        onclick="return confirm('{{ __('This will wipe the database. Continue?') }}')">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M9 7V4h6v3m2 0v13H7V7"/></svg>
                                    {{ __('Run db:reset') }}
                                </button>
                            </form>

                            @if($lastStatus)
                                <div class="rounded-2xl border px-4 py-3 text-sm {{ $lastStatus === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' }}">
                                    <p class="font-semibold">
                                        {{ __('Last command status: :status', ['status' => $lastStatus]) }}
                                    </p>
                                    @if($lastOutput)
                                        <pre class="mt-3 max-h-48 overflow-y-auto whitespace-pre-wrap rounded-xl bg-black/80 px-3 py-2 text-xs text-slate-100">{{ $lastOutput }}</pre>
                                    @endif
                                </div>
                            @endif

                            <form method="POST" action="{{ route('setup.logout') }}">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-orange-300 hover:text-orange-600">
                                    {{ __('Lock Console') }}
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
