@php
    use App\Models\NotificationDelivery;
    use App\Support\LocalTime;

    $toneClasses = [
        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
        'red' => 'border-red-200 bg-red-50 text-red-700',
    ];
    $channelLabels = [
        NotificationDelivery::CHANNEL_EMAIL => __('Email'),
        NotificationDelivery::CHANNEL_SMS => __('Text'),
        NotificationDelivery::CHANNEL_PUSH => __('Push'),
    ];
@endphp

{{-- TASK-498: who was told what, when, whether it went out, and what they did. --}}
<section id="notifications" data-test="notification-timeline" class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-sm">
    <header class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Notifications') }}</h2>
        <p class="text-xs text-slate-500">{{ __('Every text, email and push sent to your team and customers, newest first. "Sent" means the provider accepted it; a text or email cannot tell us it was read, so for job notices you see what the driver did instead.') }}</p>
    </header>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="nt-search" class="sr-only">{{ __('Search notifications') }}</label>
            <input id="nt-search" type="search" wire:model.live.debounce.400ms="search" placeholder="{{ __('Name, address or subject') }}"
                   class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
        </div>
        <div>
            <label for="nt-kind" class="sr-only">{{ __('Kind') }}</label>
            <select id="nt-kind" wire:model.live="kind" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                <option value="">{{ __('Any kind') }}</option>
                @foreach($kinds as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="nt-channel" class="sr-only">{{ __('Channel') }}</label>
            <select id="nt-channel" wire:model.live="channel" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                <option value="">{{ __('Any channel') }}</option>
                @foreach($channelLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="nt-status" class="sr-only">{{ __('Status') }}</label>
            <select id="nt-status" wire:model.live="status" class="w-full rounded-xl border border-slate-400 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                <option value="">{{ __('Sent or not') }}</option>
                <option value="{{ NotificationDelivery::STATUS_SENT }}">{{ __('Sent') }}</option>
                <option value="{{ NotificationDelivery::STATUS_NOT_DELIVERED }}">{{ __('Not delivered') }}</option>
            </select>
        </div>
    </div>

    @if($search !== '' || $kind !== '' || $channel !== '' || $status !== '')
        <button type="button" wire:click="clearFilters" class="mt-3 text-xs font-semibold text-slate-500 hover:text-orange-600">{{ __('Clear filters') }}</button>
    @endif

    @if($deliveries->isEmpty())
        <p class="mt-6 text-sm text-slate-500">
            {{ ($search !== '' || $kind !== '' || $channel !== '' || $status !== '')
                ? __('Nothing matches these filters.')
                : __('Nothing has been sent since this timeline started recording.') }}
        </p>
    @else
        <ul class="mt-5 divide-y divide-slate-100 rounded-2xl border border-slate-200">
            @foreach($deliveries as $delivery)
                @php
                    $related = $delivery->related;
                    $relatedLink = match (true) {
                        $related instanceof \App\Models\PilotCarJob => ['url' => route('my.jobs.show', $related->id), 'label' => __('Job :no', ['no' => $related->job_no ?? $related->id])],
                        $related instanceof \App\Models\Invoice => ['url' => route('my.invoices.edit', $related->id), 'label' => __('Invoice #:no', ['no' => $related->invoice_number])],
                        default => null,
                    };
                    $response = $responses[$delivery->id] ?? null;
                @endphp
                <li wire:key="delivery-{{ $delivery->id }}" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ $delivery->user?->name ?? $delivery->recipient ?? __('Unknown recipient') }}
                            <span class="ml-1 text-xs font-normal text-slate-500">{{ $delivery->kindLabel() }}</span>
                        </p>
                        @if($delivery->subject)
                            <p class="text-xs text-slate-700">{{ $delivery->subject }}</p>
                        @endif
                        @if($delivery->excerpt)
                            <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($delivery->excerpt, 160) }}</p>
                        @endif
                        <p class="mt-1 text-xs text-slate-500">
                            {{ LocalTime::format($delivery->created_at, 'M j, Y g:i A') }}
                            · {{ $channelLabels[$delivery->channel] ?? $delivery->channel }}
                            @if($delivery->recipient && $delivery->channel !== NotificationDelivery::CHANNEL_PUSH)
                                {{ __('to :address', ['address' => $delivery->recipient]) }}
                            @endif
                            @if($relatedLink)
                                · <a href="{{ $relatedLink['url'] }}" class="font-semibold text-orange-600 hover:text-orange-700 hover:underline">{{ $relatedLink['label'] }}</a>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($delivery->status === NotificationDelivery::STATUS_SENT)
                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-700">{{ __('Sent') }}</span>
                        @else
                            <span class="inline-flex rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700">{{ __('Not delivered') }}</span>
                        @endif
                        @if($response)
                            <span class="inline-flex rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $toneClasses[$response['tone']] }}">{{ $response['label'] }}</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">
            {{ $deliveries->links() }}
        </div>
    @endif
</section>
