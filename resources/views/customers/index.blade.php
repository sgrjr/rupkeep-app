@php
    $customerCount = $allCustomers->count();
    $totalContacts = $allCustomers->sum(fn ($customer) => $customer->contacts->count());
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide">{{ auth()->user()->organization->name }}</p>
                <h1 class="text-xl font-semibold">{{ __('Customer Directory') }}</h1>
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
        @if(session('customer_archive'))
            {{-- The one way back after a customer delete (TASK-461): the archive
                 file, offered here and kept on the server. --}}
            <section data-test="customer-archive" class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-amber-900">{{ __(':name has been deleted, with every contact, job and driver log it owned.', ['name' => session('customer_archive')['name']]) }}</p>
                    <p class="text-xs text-amber-800">{{ __('There is no undo. Everything is in the archive file, which also stays on the server under storage/app/private/archives/customers and is part of the backup.') }}</p>
                </div>
                <a href="{{ route('my.customers.archives.download', ['file' => session('customer_archive')['file']]) }}"
                   class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    {{ __('Download archive') }}
                </a>
            </section>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total customers') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $customerCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contacts on file') }}</p>
                <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $totalContacts }}</p>
            </div>
            <div class="rounded-3xl border border-blue-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Avg jobs per customer') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $averageJobsPerCustomer }}</p>
            </div>
            <div class="rounded-3xl border border-amber-100 bg-white/90 p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('With account credit') }}</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $customersWithCredit }}</p>
            </div>
        </section>

        @if($hasFilter && $filteredCustomers->count() > 0)
        <section class="rounded-3xl border border-blue-200 bg-blue-50/50 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Filtered Customers') }} ({{ $filterTitle }})</h2>
                    <p class="text-xs text-slate-500">{{ __('Filtered customers matching the selected criteria.') }}</p>
                </div>
            </header>

            <!-- Mobile Card Layout -->
            <div class="mt-5 space-y-3 sm:hidden">
                @foreach($filteredCustomers as $customer)
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
                                           :message="__('Deletes :name with every contact, job and driver log. There is no undo; an archive file is saved on the server and offered for download.', ['name' => $customer->name])"
                                           :confirm-label="__('Yes, archive and delete')"
                                           button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Desktop Table Layout -->
            <div class="mt-5 hidden overflow-hidden rounded-2xl border border-slate-200 sm:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-600">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Location') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Contacts') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Account Credit') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($filteredCustomers as $customer)
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
                                <td class="px-4 py-3 text-sm font-semibold {{ ($customer->account_credit ?? 0) > 0 ? 'text-blue-600' : 'text-slate-500' }}">
                                    ${{ number_format($customer->account_credit ?? 0, 2) }}
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
                                           :message="__('Deletes :name with every contact, job and driver log. There is no undo; an archive file is saved on the server and offered for download.', ['name' => $customer->name])"
                                           :confirm-label="__('Yes, archive and delete')"
                                           button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('All Customers') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Keep customer data current to streamline job assignment and invoicing.') }}</p>
                </div>
            </header>

            <!-- Mobile Card Layout -->
            <div class="mt-5 space-y-3 sm:hidden">
                @forelse($allCustomers as $customer)
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
                                <p class="mt-2 text-xs font-semibold text-blue-600">
                                    {{ __('Account Credit') }}: ${{ number_format($customer->account_credit ?? 0, 2) }}
                                </p>
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
                                           :message="__('Deletes :name with every contact, job and driver log. There is no undo; an archive file is saved on the server and offered for download.', ['name' => $customer->name])"
                                           :confirm-label="__('Yes, archive and delete')"
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
                            <th class="px-4 py-3 text-left">{{ __('Account Credit') }}</th>
                            <th class="px-4 py-3 text-left">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($allCustomers as $customer)
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
                                <td class="px-4 py-3 text-sm font-semibold {{ ($customer->account_credit ?? 0) > 0 ? 'text-blue-600' : 'text-slate-500' }}">
                                    ${{ number_format($customer->account_credit ?? 0, 2) }}
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
                                           :message="__('Deletes :name with every contact, job and driver log. There is no undo; an archive file is saved on the server and offered for download.', ['name' => $customer->name])"
                                           :confirm-label="__('Yes, archive and delete')"
                                           button-class="inline-flex items-center gap-1 rounded-full border border-red-200 bg-white px-3 py-1 text-[11px] font-semibold text-red-600 transition hover:border-red-300 hover:text-red-700" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">{{ __('No customers yet. Add your first customer to begin tracking jobs and invoices.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
