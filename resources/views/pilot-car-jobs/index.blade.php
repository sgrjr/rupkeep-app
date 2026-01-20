@props(['redirect_to_root'=>false])
<x-app-layout>
    <div>
        <div class="mx-auto space-y-8">
            <section class="relative overflow-hidden bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-4">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Pilot Car Jobs') }}</p>
                        <h1 class="text-3xl font-bold tracking-tight">
                            @if($customer)
                                {{ $customer->name }} —
                            @endif
                            {{ __('Jobs Dashboard') }}
                        </h1>
                        <p class="text-sm text-white/85">
                            {{ __('Monitor billing status, export invoices, and dive into job details for your escort team.') }}
                        </p>
                    </div>
                    <span class="rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-white/85 shadow-sm backdrop-blur">
                        {{ trans_choice('{0} No jobs|{1} :count job|[2,*] :count jobs', $totalJobs, ['count' => $totalJobs]) }}
                    </span>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 px-6">
                <div class="rounded-3xl border border-orange-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Total jobs') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $totalJobs }}</p>
                </div>
                <div class="rounded-3xl border border-emerald-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Paid jobs') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $paidJobs }}</p>
                </div>
                <div class="rounded-3xl border border-rose-100 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Unpaid jobs') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $unpaidJobs }}</p>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-white/90 p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Canceled') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $canceledJobs }}</p>
                </div>
                @if(isset($missingJobNo) && $missingJobNo > 0)
                <a href="{{ route('my.jobs.index', ['search_field' => 'missing_job_no', 'search_value' => '']) }}" class="rounded-3xl border border-amber-200 bg-amber-50/90 p-4 shadow-sm transition hover:border-amber-300 hover:bg-amber-100">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('Missing Job #') }}</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-900">{{ $missingJobNo }}</p>
                </a>
                @endif
            </section>

            @can('viewAny', \App\Models\Invoice::class)
                <section class="rounded-3xl border border-slate-200 bg-white/90 m-6 p-6 shadow-sm">
                    <header class="mb-4">
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('QuickBooks Export') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Filter invoices by date, customer, or payment status and export a detailed CSV ready for QuickBooks import.') }}</p>
                    </header>
                    <form method="GET" action="{{ route('my.invoices.export.quickbooks') }}" class="grid gap-4 md:grid-cols-6">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('From Date') }}</label>
                            <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('To Date') }}</label>
                            <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Customer') }}</label>
                            <select name="customer_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('All Customers') }}</option>
                                @foreach(\App\Models\Customer::where('organization_id', auth()->user()->organization_id)->orderBy('name')->get() as $customer)
                                    <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600 mb-1">{{ __('Status') }}</label>
                            <select name="paid" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                <option value="">{{ __('Any Status') }}</option>
                                <option value="yes" @selected(request('paid') === 'yes')>{{ __('Paid') }}</option>
                                <option value="no" @selected(request('paid') === 'no')>{{ __('Unpaid') }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 flex items-end justify-end gap-2">
                            <x-button type="submit" variant="secondary" class="w-full md:w-auto">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582c1.356 0 2.643.543 3.596 1.508L12 12.83l3.822-1.872A4.999 4.999 0 0 1 19.418 9H20V4M4 20h16M4 16h16"/></svg>
                                {{ __('Export CSV') }}
                            </x-button>
                        </div>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">
                        {{ __('The export includes invoice details, line items, expenses, and payment information formatted for QuickBooks import.') }}
                    </p>
                </section>
            @endcan

            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 m-6 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <a href="{{ route('my.jobs.index', ['filter' => 'recent']) }}" class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm transition {{ request('filter') === 'recent' ? 'border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        {{ __('Recent Jobs') }}
                    </a>
                </div>
                
                <form method="GET" action="{{ route('my.jobs.index') }}" id="filterForm" class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div class="md:w-56">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Filter/Scope') }}</label>
                        <select name="filter" onchange="document.getElementById('filterForm').submit();" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <option value="">{{ __('All Jobs') }}</option>
                            <option value="recent" @selected(request('filter') === 'recent')>{{ __('Recent (Newest First)') }}</option>
                            <option value="is_paid" @selected(request('filter') === 'is_paid')>{{ __('Paid Jobs') }}</option>
                            <option value="is_not_paid" @selected(request('filter') === 'is_not_paid')>{{ __('Unpaid Jobs') }}</option>
                            <option value="is_canceled" @selected(request('filter') === 'is_canceled')>{{ __('Canceled Jobs') }}</option>
                            <option value="missing_job_no" @selected(request('filter') === 'missing_job_no')>{{ __('Missing Job #') }}</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Search Jobs') }}</label>
                        <input type="text" name="search_value" value="{{ request('search_value') }}" placeholder="{{ __('Search by keyword') }}" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" />
                    </div>
                    <div class="md:w-56">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Field') }}</label>
                        <select name="search_field" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <option value="has_customer_name" @selected(request('search_field') === 'has_customer_name')>{{ __('Customer Name') }}</option>
                            <option value="job_no" @selected(request('search_field') === 'job_no')>{{ __('Job #') }}</option>
                            <option value="load_no" @selected(request('search_field') === 'load_no')>{{ __('Load #') }}</option>
                            <option value="invoice_no" @selected(request('search_field') === 'invoice_no')>{{ __('Invoice #') }}</option>
                            <option value="check_no" @selected(request('search_field') === 'check_no')>{{ __('Check #') }}</option>
                            <option value="delivery_address" @selected(request('search_field') === 'delivery_address')>{{ __('Delivery Address') }}</option>
                            <option value="pickup_address" @selected(request('search_field') === 'pickup_address')>{{ __('Pickup Address') }}</option>
                        </select>
                    </div>
                    <div class="md:w-auto">
                        <x-button type="submit">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>
                            {{ __('Search') }}
                        </x-button>
                    </div>
                    @if(request('search_field'))
                        <input type="hidden" name="search_field" value="{{ request('search_field') }}">
                    @endif
                    @if(request('filter'))
                        <input type="hidden" name="filter" value="{{ request('filter') }}">
                    @endif
                </form>
                <div class="mt-4 flex items-center justify-end">
                    <form method="GET" action="{{ route('my.jobs.index') }}" class="inline-flex">
                        <input type="hidden" name="show_deleted" value="{{ $showDeleted ? '0' : '1' }}">
                        @if(request('search_field'))
                            <input type="hidden" name="search_field" value="{{ request('search_field') }}">
                            <input type="hidden" name="search_value" value="{{ request('search_value') }}">
                        @endif
                        @if(request('filter'))
                            <input type="hidden" name="filter" value="{{ request('filter') }}">
                        @endif
                        <button type="submit" 
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold shadow-sm transition {{ $showDeleted ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                            {{ $showDeleted ? __('Hide Archived') : __('Show Archived') }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-3 px-4 sm:px-6">
                @foreach($jobs as $job)
                    @php
                        $showRoute = $redirect_to_root
                            ? route('jobs.show', ['job' => $job->id])
                            : route('my.jobs.show', ['job' => $job->id]);
                        $editRoute = route('my.jobs.edit', ['job' => $job->id]);
                        $destroyRoute = route('my.jobs.destroy', ['job' => $job->id]);
                        $isPaid = $job->invoice_paid >= 1;
                        $pillClasses = $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700';
                        $pillText = $isPaid ? __('Paid') : __('Unpaid');
                        
                        // Job status
                        $jobStatus = $job->status;
                        $statusBadges = [
                            'ACTIVE' => ['class' => 'bg-blue-100 text-blue-700', 'text' => __('Active')],
                            'CANCELLED' => ['class' => 'bg-red-100 text-red-700', 'text' => __('Cancelled')],
                            'CANCELLED_NO_GO' => ['class' => 'bg-amber-100 text-amber-700', 'text' => __('Cancelled (No-Go)')],
                            'COMPLETED' => ['class' => 'bg-emerald-100 text-emerald-700', 'text' => __('Completed')],
                        ];
                        $statusBadge = $statusBadges[$jobStatus] ?? ['class' => 'bg-slate-100 text-slate-700', 'text' => __('Unknown')];
                        
                        $jobSummary = collect([
                            $job->customer?->name,
                            $job->load_no ? __('Load #:number', ['number' => $job->load_no]) : null,
                            $job->rate_code,
                        ])->filter()->implode(' • ');
                    @endphp
                    <article class="flex h-full flex-col justify-between overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg">
                        <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Job') }}</p>
                                    <p class="text-lg font-bold text-slate-900">{{ $job->job_no }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $pillClasses }}">{{ $pillText }}</span>
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $statusBadge['class'] }}">{{ $statusBadge['text'] }}</span>
                                </div>
                            </div>
                            @if($job->customer)
                                <p class="mt-2 text-sm text-slate-500">
                                    <span class="font-semibold text-slate-200">{{ __('Summary') }}</span>
                                    <span class="ml-2 text-white/90">{{ $jobSummary }}</span>
                                </p>
                            @endif
                        </div>
                        <div class="space-y-2 px-5 py-4 text-sm text-slate-600">
                            @can('viewAny', new \App\Models\Organization)
                                <p class="hidden md:block"><span class="font-semibold text-slate-900">{{ __('Organization') }}:</span> {{ $job->organization->name }}</p>
                            @endcan
                            <p><span class="font-semibold text-slate-900">{{ __('Load #') }}:</span> {{ $job->load_no ?? '—' }}</p>
                            <p><span class="font-semibold text-slate-900">{{ __('Scheduled') }}:</span> 
                                <span class="text-slate-900">{{ optional($job->scheduled_pickup_at)->format('M j, Y g:i A') ?? $job->scheduled_pickup_at ?? '—' }}</span>
                            </p>
                            <p><span class="font-semibold text-slate-900">{{ __('Pickup') }}:</span> 
                                <span class="text-slate-600">{{ \Illuminate\Support\Str::limit($job->pickup_address ?? '—', 40) }}</span>
                            </p>
                            <p class="hidden sm:block"><span class="font-semibold text-slate-900">{{ __('Delivery') }}:</span> 
                                <span class="text-slate-600">{{ \Illuminate\Support\Str::limit($job->delivery_address ?? '—', 40) }}</span>
                            </p>
                            <p class="hidden md:block"><span class="font-semibold text-slate-900">{{ __('Scheduled Delivery') }}:</span> {{ optional($job->scheduled_delivery_at)->format('M j, Y g:i A') ?? $job->scheduled_delivery_at ?? '—' }}</p>
                            <p class="hidden md:block"><span class="font-semibold text-slate-900">{{ __('Invoice #') }}:</span> {{ $job->invoice_no ?? '—' }}</p>
                            <p class="hidden md:block"><span class="font-semibold text-slate-900">{{ __('Check #') }}:</span> {{ $job->check_no ?? '—' }}</p>
                            <p class="hidden lg:block"><span class="font-semibold text-slate-900">{{ __('Rate Code') }}:</span> {{ $job->rate_code ?? '—' }}</p>
                            <p class="hidden lg:block"><span class="font-semibold text-slate-900">{{ __('Rate Value') }}:</span>
                                {{ $job->rate_value !== null ? '$'.number_format((float) $job->rate_value, 2) : '—' }}
                            </p>
                            @php
                                $miles = $job->miles;
                            @endphp
                            @if($miles && ($miles->total > 0 || $miles->billable > 0))
                                <p><span class="font-semibold text-slate-900">{{ __('Miles') }}:</span> 
                                    <span class="text-slate-600">{{ number_format($miles->billable ?? 0, 1) }} {{ __('billable') }}</span>
                                    @if($miles->total > 0)
                                        <span class="text-slate-400">/ {{ number_format($miles->total, 1) }} {{ __('total') }}</span>
                                    @endif
                                </p>
                            @endif
                            @if($job->canceled_at)
                                <p class="text-sm font-semibold text-rose-600">{{ __('Canceled At') }}: <span class="font-normal text-slate-700">{{ $job->canceled_at }}</span></p>
                            @endif
                            @if($job->canceled_reason)
                                <p class="text-sm font-semibold text-rose-600">{{ __('Cancellation Reason') }}: <span class="font-normal text-slate-700">{{ $job->canceled_reason }}</span></p>
                            @endif
                            <p class="pt-1"><span class="font-semibold text-slate-900">{{ __('Memo') }}:</span>
                                @if(str_starts_with($job->memo ?? '', 'http'))
                                    <a target="_blank" href="{!!$job->memo!!}" class="text-orange-600 hover:text-orange-700">{{ __('View Link') }}</a>
                                @else
                                    {{ $job->memo ?? '—' }}
                                @endif
                            </p>
                        </div>
                        <div class="border-t border-slate-100 bg-slate-50 px-5 py-4">
                            <div class="flex flex-wrap justify-end gap-2">
                                @if($job->trashed())
                                    <span class="inline-flex items-center gap-2 rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[11px] font-semibold text-red-700">
                                        {{ __('Archived') }}
                                    </span>
                                    @can('restore', $job)
                                        <livewire:restore-button
                                            :action-url="route('my.jobs.restore', $job->id)"
                                            button-text="{{ __('Restore') }}"
                                            :model-class="\App\Models\PilotCarJob::class"
                                            :record-id="$job->id"
                                            resource="jobs"
                                            :redirect-route="$redirect_to_root ? 'jobs.index' : 'my.jobs.index'"
                                        />
                                    @endcan
                                @else
                                    <a href="{{ $showRoute }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                        {{ __('Show') }}
                                    </a>
                                    @can('update', $job)
                                        <a href="{{ $editRoute }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-semibold text-slate-600 transition hover:border-orange-300 hover:text-orange-600">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                    @can('delete', $job)
                                        <livewire:delete-confirmation-button
                                            :action-url="$destroyRoute"
                                            button-text="{{ __('Delete') }}"
                                            :model-class="\App\Models\PilotCarJob::class"
                                            :record-id="$job->id"
                                            resource="jobs"
                                            :redirect-route="$redirect_to_root ? 'jobs.index' : 'my.jobs.index'"
                                        />
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </article>
            @endforeach
            </section>
            
            @if($jobs->hasPages())
                <div class="px-4 sm:px-6">
                    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
                        <div class="flex flex-1 justify-between sm:hidden">
                            @if($jobs->onFirstPage())
                                <span class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-500 cursor-not-allowed">
                                    {{ __('Previous') }}
                                </span>
                            @else
                                <a href="{{ $jobs->appends(request()->query())->previousPageUrl() }}" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    {{ __('Previous') }}
                                </a>
                            @endif
                            
                            @if($jobs->hasMorePages())
                                <a href="{{ $jobs->appends(request()->query())->nextPageUrl() }}" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                    {{ __('Next') }}
                                </a>
                            @else
                                <span class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-500 cursor-not-allowed">
                                    {{ __('Next') }}
                                </span>
                            @endif
                        </div>
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-slate-700">
                                    {{ __('Showing') }}
                                    <span class="font-medium">{{ $jobs->firstItem() }}</span>
                                    {{ __('to') }}
                                    <span class="font-medium">{{ $jobs->lastItem() }}</span>
                                    {{ __('of') }}
                                    <span class="font-medium">{{ $jobs->total() }}</span>
                                    {{ __('results') }}
                                </p>
                            </div>
                            <div>
                                {{ $jobs->appends(request()->except('page'))->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif
    </div>
</div>
</x-app-layout>