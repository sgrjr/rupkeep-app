@php
    $customerCount = $customers->count();
    $totalContacts = $customers->sum(fn ($customer) => $customer->contacts->count());
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Accounts Directory') }}</p>
                <h1 class="text-xl font-semibold text-slate-900">{{ __('Customers') }}</h1>
                <p class="text-xs text-slate-500">{{ trans_choice('You manage :count customer|You manage :count customers', $customerCount) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('my.customers.create') }}"
                   class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-500 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-orange-600">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Add customer') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total customers') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $customerCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contacts on file') }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $totalContacts }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Customer Accounts') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Keep customer data current to streamline job assignment and invoicing.') }}</p>
                </div>
            </header>

            <!-- Mobile Card Layout -->
            <div class="mt-5 space-y-3 sm:hidden">
                @forelse($customers as $customer)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('my.customers.show', ['customer' => $customer->id]) }}" class="font-semibold text-orange-600 hover:text-orange-700 truncate block">
                                    {{ $customer->name }}
                                </a>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $customer->city ?? '—' }}, {{ $customer->state ?? '—' }}
                                </p>
                                <span class="mt-2 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-600">
                                    {{ trans_choice(':count contact|:count contacts', $customer->contacts->count()) }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <a href="{{ route('my.jobs.index', ['customer' => $customer->id]) }}"
                               class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                {{ __('Jobs') }}
                            </a>
                            <a href="{{ route('customers.edit', ['customer' => $customer->id]) }}"
                               class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                {{ __('Edit') }}
                            </a>
                            <x-delete-form action="{{ route('my.customers.destroy', ['customer' => $customer->id]) }}"
                                           title="{{ __('Delete') }}"
                                           button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-400">
                        {{ __('No customers yet. Add your first customer to begin tracking jobs and invoices.') }}
                    </div>
                @endforelse
            </div>

            <!-- Desktop Table Layout -->
            <div class="mt-5 hidden overflow-hidden rounded-2xl border border-slate-200 sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Location') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Contacts') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($customers as $customer)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-800">
                                    <a href="{{ route('my.customers.show', ['customer' => $customer->id]) }}" class="text-orange-600 hover:text-orange-700">
                                        {{ $customer->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ $customer->city ?? '—' }}, {{ $customer->state ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                        {{ trans_choice(':count contact|:count contacts', $customer->contacts->count()) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('my.jobs.index', ['customer' => $customer->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            {{ __('Jobs') }}
                                        </a>
                                        <a href="{{ route('customers.edit', ['customer' => $customer->id]) }}"
                                           class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"/></svg>
                                            {{ __('Edit') }}
                                        </a>
                                        <x-delete-form action="{{ route('my.customers.destroy', ['customer' => $customer->id]) }}"
                                                       title="{{ __('Delete') }}"
                                                       button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No customers yet. Add your first customer to begin tracking jobs and invoices.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
