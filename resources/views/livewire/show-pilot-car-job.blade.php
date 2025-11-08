@props(['drivers'=>[], 'vehicles'=>[]])

<div class="bg-slate-100/80 pb-32">
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Pilot Car Job') }}</p>
                    <h1 class="text-3xl font-bold tracking-tight">{{ $job->job_no ?? __('Unnumbered Job') }}</h1>
                    <div class="flex flex-wrap items-center gap-3 text-sm text-white/85">
                        @if($job->customer_id)
                            <a href="{{route('my.customers.show', ['customer'=>$job->customer_id])}}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                {{ $job->customer->name }}
                            </a>
                        @endif
                        <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold transition hover:bg-white/25">
                            {{ __('Jobs Index') }}
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <div class="grid gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-white/85 shadow-sm backdrop-blur sm:grid-cols-2">
                    <div>
                        {{ __('Pickup') }}
                        <p class="mt-1 text-sm font-medium normal-case text-white">
                            {{ optional($job->scheduled_pickup_at)->format('M j, Y g:i A') ?? '—' }}
                        </p>
                    </div>
                    <div>
                        {{ __('Delivery') }}
                        <p class="mt-1 text-sm font-medium normal-case text-white">
                            {{ optional($job->scheduled_delivery_at)->format('M j, Y g:i A') ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @if(auth()->user()->can('update', $job))
                <a href="{{route('my.jobs.edit', ['job'=>$job->id])}}" class="group relative overflow-hidden rounded-3xl border border-orange-100 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-orange-200 hover:shadow-lg">
                    <div class="absolute right-0 top-0 h-24 w-24 -translate-y-12 translate-x-6 rounded-full bg-orange-100 opacity-60 blur-3xl transition group-hover:opacity-80"></div>
                    <div class="relative flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Manage') }}</p>
                            <p class="mt-2 text-lg font-bold text-slate-900">{{ __('Edit Job') }}</p>
                        </div>
                        <span class="rounded-full bg-orange-50 p-2 text-orange-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-4.536a2.5 2.5 0 11-3.536 3.536L4.5 16.5V19.5H7.5l8.5-8.5"></path></svg>
                        </span>
                    </div>
                </a>
            @endif

            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-orange-400 to-orange-600"></div>
                <form wire:submit="generateInvoice" class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Invoices') }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Generate Invoice') }}</p>
                        </div>
                        <x-action-message class="text-xs font-semibold text-emerald-600" on="updated">
                            {{ __('Created') }}
                        </x-action-message>
                    </div>
                    <x-button type="submit">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 11h16M10 15h10M6 19h8"/></svg>
                        {{ __('Create Invoice') }}
                    </x-button>
                </form>
            </div>

            <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-2">
                <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-slate-200 to-slate-300"></div>
                <form wire:submit="uploadFile" class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Attachments') }}</p>
                    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="file" wire:model="file" class="grow rounded-xl border border-dashed border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none" />
                        <x-button type="submit" variant="ghost">
                            <span wire:loading wire:target="uploadFile" class="h-4 w-4 animate-spin rounded-full border-2 border-white/80 border-t-transparent"></span>
                            {{ __('Attach File') }}
                        </x-button>
                        <x-action-message class="text-xs font-semibold text-emerald-600" on="uploaded">
                            {{ __('Uploaded') }}
                        </x-action-message>
                    </div>
                    @error('file') <p class="text-xs font-semibold text-red-500">{{ $message }}</p> @enderror
                </form>
            </div>

            @if(auth()->user()->can('delete', $job))
                <div class="rounded-3xl border border-red-100 bg-white/90 p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-400">{{ __('Danger Zone') }}</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ __('Delete Job') }}</p>
                    <p class="mt-2 text-xs text-slate-500">{{ __('Removes this job and all associated logs. This cannot be undone.') }}</p>
                    <div class="mt-4">
                        <livewire:delete-confirmation-button
                            :action-url="route('my.jobs.destroy', ['job'=> $job->id])"
                            button-text="{{ __('Delete Job') }}"
                            :model-class="\App\Models\PilotCarJob::class"
                            :record-id="$job->id"
                            resource="jobs"
                            :redirect-route="($redirect_to_root ?? false) ? 'jobs.index' : 'my.jobs.index'"
                        />
                    </div>
                </div>
            @endif
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Overview') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Customer, schedule, and financial information for this job.') }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ __('Rate: :code', ['code' => $job->rate_code ?? '—']) }}</span>
            </header>

            <div class="grid gap-4 md:grid-cols-2">
                <article class="space-y-3 text-sm text-slate-600">
                    @can('viewAny', new \App\Models\Organization)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Organization') }}:</span> <span class="text-slate-900">{{ $job->organization->name }}</span></p>
                    @endcan
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Job #') }}:</span> <span class="text-slate-900 font-semibold">{{ $job->job_no }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Load #') }}:</span> <span class="text-slate-900">{{ $job->load_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Customer') }}:</span> <span class="text-slate-900">{{ $job->customer?->name ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Check #') }}:</span> <span class="text-slate-900">{{ $job->check_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice Paid') }}:</span> <span class="font-semibold {{ $job->invoice_paid < 1 ? 'text-red-500' : 'text-emerald-600' }}">{{ $job->invoice_paid < 1 ? __('No') : __('Yes') }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoice #') }}:</span> <span class="text-slate-900">{{ $job->invoice_no ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Rate Value') }}:</span> <span class="text-slate-900">{{ $job->rate_value !== null ? '$'.number_format((float) $job->rate_value, 2) : '—' }}</span></p>
                    @if($job->canceled_at)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-red-500">{{ __('Canceled At') }}:</span> <span class="text-red-600">{{ $job->canceled_at }}</span></p>
                    @endif
                    @if($job->canceled_reason)
                        <p><span class="text-xs font-semibold uppercase tracking-wide text-red-500">{{ __('Cancellation Reason') }}:</span> <span class="text-red-600">{{ $job->canceled_reason }}</span></p>
                    @endif
                </article>

                <article class="space-y-3 text-sm text-slate-600">
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$job->pickup_address}}">{{ $job->pickup_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Pickup Time') }}:</span> <span class="text-slate-900">{{ $job->scheduled_pickup_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery') }}:</span> <a class="text-orange-600 hover:text-orange-700" target="_blank" href="http://maps.google.com/?daddr={{$job->delivery_address}}">{{ $job->delivery_address ?? '—' }}</a></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Delivery Time') }}:</span> <span class="text-slate-900">{{ $job->scheduled_delivery_at ?? '—' }}</span></p>
                    <p><span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Memo') }}:</span>
                        @if(str_starts_with($job->memo ?? '', 'http'))
                            <a target="_blank" href="{!!$job->memo!!}" class="text-orange-600 hover:text-orange-700">{{ __('View Link') }}</a>
                        @else
                            <span class="text-slate-900">{{ $job->memo ?? '—' }}</span>
                        @endif
                    </p>
                    <div class="pt-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Invoices') }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @forelse($job->invoices as $invoice)
                                <a target="_blank" href="{{route('my.invoices.edit', ['invoice'=>$invoice->id])}}" class="inline-flex items-center gap-2 rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600 transition hover:bg-orange-500 hover:text-white">
                                    {{ __('Invoice #:number', ['number' => $invoice->invoice_number]) }}
                                </a>
                            @empty
                                <span class="text-xs text-slate-400">{{ __('No invoices yet.') }}</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            </div>

            @if($job->customer)
                <details class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <summary class="cursor-pointer text-sm font-semibold text-orange-600 hover:text-orange-700">{{ __('Customer Contact Info') }}</summary>
                    <div class="mt-3 space-y-3 text-sm text-slate-600">
                        <p>{{ $job->customer->street }} {{ $job->customer->city }}, {{ $job->customer->state }} {{ $job->customer->zip }}</p>
                        <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                            <table class="min-w-full divide-y divide-slate-200 text-xs">
                                <thead class="bg-slate-100 text-slate-500">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Name') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Phone') }}</th>
                                        <th class="px-4 py-2 text-left font-semibold uppercase tracking-wider">{{ __('Memo') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($job->customer->contacts as $contact)
                                        <tr>
                                            <td class="px-4 py-2 text-slate-700">{{ $contact->name }}</td>
                                            <td class="px-4 py-2 text-slate-700">{{ $contact->phone }}</td>
                                            <td class="px-4 py-2 text-slate-500">{{ $contact->memo }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-3 text-center text-slate-400">{{ __('No contacts recorded.') }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            @endif

            <div class="mt-6">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Attachments (:count)', ['count' => $job->attachments? $job->attachments->count():0]) }}</p>
                <div class="mt-3 space-y-3">
                    @forelse($job->attachments as $att)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                            <div class="min-w-0 flex-1">
                                <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">
                                    {{ $att->file_name }}
                                </a>
                                <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('updateVisibility', $att)
                                    <livewire:attachment-visibility-toggle :attachment="$att" :key="'job-att-'.$att->id"/>
                                @else
                                    @if($att->is_public)
                                        <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
                                    @endif
                                @endcan
                                <livewire:delete-confirmation-button
                                    :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                    button-text="&times;"
                                    :redirect-route="Route::currentRouteName()"
                                    :model-class="\App\Models\Attachment::class"
                                    :record-id="$att->id"
                                    resource="attachments"
                                />
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400">{{ __('No attachments yet. Upload permits, maps, or photos from the actions above.') }}</p>
                    @endforelse
                </div>
            </div>
        </section>

        @if($job->logs)
            <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Logs') }}</h2>
                        <p class="text-xs text-slate-500">{{ __('Assign drivers and manage daily records for this job.') }}</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ trans_choice('{0} No logs|{1} :count log|[2,*] :count logs', $job->logs->count(), ['count' => $job->logs->count()]) }}</span>
                </header>

                <div class="mt-6 space-y-6">
                    <form wire:submit="assignJob" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Assign driver & vehicle') }}</label>
                            <div class="grow">
                                <select wire:model.blur="assignment.car_driver_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200" required>
                                    <option value="">{{ __('Select Driver') }}</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver['value'] }}">{{ $driver['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grow">
                                <select wire:model.blur="assignment.vehicle_id" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select Vehicle') }}</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle['value'] }}">{{ $vehicle['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grow">
                                <select wire:model.blur="assignment.vehicle_position" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                                    <option value="">{{ __('Select Position') }}</option>
                                    @foreach($vehicle_positions as $position)
                                        <option value="{{ $position['value'] }}">{{ $position['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-action-message class="text-xs font-semibold text-emerald-600" on="updated">
                                {{ __('Assigned') }}
                            </x-action-message>
                            <x-button type="submit">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                {{ __('Assign') }}
                            </x-button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        @foreach($job->logs as $log)
                            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Log ID') }}: {{ $log->id }}</p>
                                        <p class="text-sm font-semibold text-slate-900">{{ optional($log->created_at)->toFormattedDateString() }}</p>
                                        <p class="text-xs text-slate-500">{{ __('Driver') }}: {{ $log->driver?->name ?? '—' }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if(auth()->user()->can('update', $log))
                                            <x-button type="button" variant="ghost" onclick="window.location='{{ route('logs.edit', ['log'=>$log->id]) }}'">
                                                {{ __('Edit Log') }}
                                            </x-button>
                                        @endif
                                        @if(auth()->user()->can('delete', $log))
                                            <livewire:delete-confirmation-button
                                                :action-url="route('logs.destroy', ['log'=> $log->id])"
                                                button-text="{{ __('Delete') }}"
                                                :redirect-route="Route::currentRouteName()"
                                                :model-class="\App\Models\UserLog::class"
                                                :record-id="$log->id"
                                                resource="logs"
                                            />
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <p><span class="font-semibold text-slate-900">{{ __('Start Mileage') }}:</span> {{ $log->start_mileage ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('End Mileage') }}:</span> {{ $log->end_mileage ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Total Miles') }}:</span> {{ $log->total_miles ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Billable Override') }}:</span> {{ $log->billable_miles ?? '—' }}</p>
                                    </div>
                                    <div class="space-y-1 text-sm text-slate-600">
                                        <p><span class="font-semibold text-slate-900">{{ __('Deadhead') }}:</span> {{ $log->dead_head_times ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Extra Stops') }}:</span> {{ $log->extra_load_stops ?? '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Tolls') }}:</span> {{ $log->tolls ? '$'.number_format($log->tolls, 2) : '—' }}</p>
                                        <p><span class="font-semibold text-slate-900">{{ __('Hotel') }}:</span> {{ $log->hotel ? '$'.number_format($log->hotel, 2) : '—' }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 text-sm text-slate-600">
                                    <p class="font-semibold text-slate-900">{{ __('Memo') }}</p>
                                    <p class="mt-1 text-slate-500">{{ $log->memo ?? __('No memo recorded.') }}</p>
                                </div>

                                @if($log->attachments?->count())
                                    <div class="mt-4 space-y-2">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ __('Log Attachments') }}</p>
                                        @foreach($log->attachments as $att)
                                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                                <div class="min-w-0 flex-1">
                                                    <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" download href="{{route('attachments.download', ['attachment'=>$att->id])}}">{{ $att->file_name }}</a>
                                                    <p class="text-xs text-slate-500">{{ $att->created_at->diffForHumans() }}</p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @can('updateVisibility', $att)
                                                        <livewire:attachment-visibility-toggle :attachment="$att" :key="'job-log-att-'.$att->id"/>
                                                    @else
                                                        @if($att->is_public)
                                                            <span class="text-xs font-semibold text-emerald-600">{{ __('Visible to customer') }}</span>
                                                        @endif
                                                    @endcan
                                                    <livewire:delete-confirmation-button
                                                        :action-url="route('attachments.destroy', ['attachment'=> $att->id])"
                                                        button-text="&times;"
                                                        :redirect-route="Route::currentRouteName()"
                                                        :model-class="\App\Models\Attachment::class"
                                                        :record-id="$att->id"
                                                        resource="attachments"
                                                    />
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>