@props(['customers'=>[], 'rates'=>[]])
@php use Illuminate\Support\Str; @endphp

<div class="mx-auto max-w-6xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-orange-500 via-orange-400 to-orange-300 p-6 text-white shadow-xl">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.25),_transparent_60%)] opacity-70"></div>
        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/75">{{ __('Edit Pilot Car Job') }}</p>
                <h1 class="text-3xl font-bold tracking-tight">{{ $job->job_no ?? __('New Job') }}</h1>
                <p class="text-sm text-white/85">{{ __('Update schedule, locations, and rate info for this pilot car assignment.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($job->customer_id)
                    <a href="{{ route('my.customers.show', ['customer'=>$job->customer_id]) }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white/85 transition hover:bg-white/25">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        {{ $job->customer->name }}
                    </a>
                @endif
                <a href="{{ route('my.jobs.index') }}" class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white/85 transition hover:bg-white/25">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H5m7-7l-7 7 7 7"/></svg>
                    {{ __('Back to jobs') }}
                </a>
            </div>
        </div>
    </section>

    <form wire:submit="saveJob" method="post" class="space-y-8">
        @csrf

        <section class="rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Job Basics') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Assign the job to a customer and capture reference numbers.') }}</p>
                </div>
            </header>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Customer') }}</label>
                    <select wire:model.blur="form.customer_id" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('form.customer_id')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('New Customer Name') }}</label>
                    <input type="text" id="new_customer_name" wire:model.blur="form.new_customer_name" placeholder="{{ __('Use only if customer is not listed') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('form.new_customer_name')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="job_no" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Job Number') }}</label>
                    <input type="text" id="job_no" wire:model="form.job_no" placeholder="JOB-2025-001" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('form.job_no')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="load_no" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Load Number') }}</label>
                    <input type="text" id="load_no" wire:model="form.load_no" placeholder="LOAD-845" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('form.load_no')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Schedule & Locations') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Track pickup/drop-off timing and addresses.') }}</p>
                </div>
            </header>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="scheduled_pickup_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Pickup Date & Time') }}</label>
                    <input type="text" id="scheduled_pickup_at" wire:model="form.scheduled_pickup_at" placeholder="{{ __('Select date + time') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('form.scheduled_pickup_at')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="scheduled_delivery_at" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Delivery Date & Time') }}</label>
                    <input type="text" id="scheduled_delivery_at" wire:model="form.scheduled_delivery_at" placeholder="{{ __('Select date + time') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    @error('form.scheduled_delivery_at')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="pickup_address" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Pickup Address') }}</label>
                    <textarea id="pickup_address" wire:model="form.pickup_address" rows="3" placeholder="{{ __('Enter pickup street, city, state') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                    @error('form.pickup_address')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="delivery_address" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Delivery Address') }}</label>
                    <textarea id="delivery_address" wire:model="form.delivery_address" rows="3" placeholder="{{ __('Enter delivery street, city, state') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                    @error('form.delivery_address')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="memo" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Internal Memo') }}</label>
                    <textarea id="memo" wire:model="form.memo" rows="4" placeholder="{{ __('Optional notes for staff reference') }}" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200"></textarea>
                    @error('form.memo')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-100 bg-white/80 p-6 shadow-sm">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ __('Rate & Billing') }}</h2>
                    <p class="text-xs text-slate-500">{{ __('Choose the appropriate rate code and override if required.') }}</p>
                </div>
            </header>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Code') }}</label>
                    <select wire:model="form.rate_code" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                        @foreach($rates as $rate)
                            <option value="{{ $rate->value }}">{{ $rate->title }}</option>
                        @endforeach
                    </select>
                    @error('form.rate_code')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="rate_value" class="block text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Rate Value / Override') }}</label>
                    <input type="text" id="rate_value" wire:model="form.rate_value" placeholder="e.g. 2.25" class="mt-2 block w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                    <p class="mt-1 text-xs text-slate-400">{{ __('Leave blank to use the default value for the selected rate.') }}</p>
                    @error('form.rate_value')
                        <p class="mt-1 text-xs font-semibold text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-action-message class="text-sm font-semibold text-emerald-500" on="saved">
                {{ __('Changes saved successfully.') }}
            </x-action-message>
            <x-button>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ __('Save Changes') }}
            </x-button>
        </div>
    </form>
</div>

@script
<script>

flatpickr('#scheduled_pickup_at', {
    "dateFormat": "Y-m-d H:i",
    "enableTime":true,
    "defaultHour": 6
});

flatpickr('#scheduled_delivery_at', {
    //"dateFormat": "m/d/y H:i",
    "enableTime":true,
    "defaultHour": 6
});

</script>
@endscript