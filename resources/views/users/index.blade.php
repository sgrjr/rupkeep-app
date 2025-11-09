@php
    $roleStats = $users->groupBy('role_label')->map->count();
    $organizationUserCount = $users->count();
    $superView = $all_users && is_iterable($all_users);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Team Directory') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('My Users') }}</h1>
                <p class="text-xs text-slate-500">{{ trans_choice('You have :count active teammate|You have :count active teammates', $organizationUserCount) }}</p>
                        </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('my.users.create') }}"
                   class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-500 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Add user') }}
                </a>
                        </div>
                    </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total users') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $organizationUserCount }}</p>
            </div>
            @foreach($roleStats as $role => $count)
                <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $role }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $count }}</p>
                </div>
            @endforeach
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Organization users') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Invite team members, update roles, or impersonate accounts for troubleshooting.') }}</p>
                </div>
            </header>

            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Role') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($users as $user)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $user->email }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                        {{ $user->role_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('user.profile', ['user' => $user->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                            {{ __('Edit') }}
                                        </a>
                                        @can('impersonate', $user)
                                            <a href="{{ route('impersonate', ['user' => $user->id]) }}"
                                               class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-white px-3 py-1 text-[11px] font-semibold text-purple-600 transition hover:border-purple-300 hover:text-purple-700">
                                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.656 0 3-1.567 3-3.5S13.656 4 12 4 9 5.567 9 7.5 10.344 11 12 11zM6 18c0-2.21 2.686-4 6-4s6 1.79 6 4"/></svg>
                                                {{ __('Impersonate') }}
                                            </a>
                                @endcan
                                        <x-delete-form action="{{ route('my.users.destroy', ['user' => $user->id]) }}"
                                                       title="{{ __('Delete') }}"
                                                       button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No users found for your organization yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                            </div>
        </section>

        @if($superView)
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('All application users') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Visible only to super administrators.') }}</p>
                            </div>
                </header>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Email') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Role') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Organization') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($all_users as $user)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $user->email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                            {{ $user->role_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ $user->organization?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('user.profile', ['user' => $user->id]) }}"
                                               class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                                {{ __('Edit') }}
                                            </a>
                                            @can('impersonate', $user)
                                                <a href="{{ route('impersonate', ['user' => $user->id]) }}"
                                                   class="inline-flex items-center gap-1 rounded-full border border-purple-200 bg-white px-3 py-1 text-[11px] font-semibold text-purple-600 transition hover:border-purple-300 hover:text-purple-700">
                                                    {{ __('Impersonate') }}
                                                </a>
                                            @endcan
                        </div>
                                    </td>
                                </tr>
                    @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
                @endif
    </div>
</x-app-layout>
