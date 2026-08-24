{{-- TASK-330: named ad-hoc charges on a driver log. Each row becomes its own invoice line item. --}}
<div class="space-y-2">
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">{{ __('Extra Charges') }}</p>

    @forelse($charges as $charge)
        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <span class="min-w-0 flex-1 truncate text-sm text-slate-900">{{ $charge->description }}</span>
            <span class="shrink-0 text-sm font-semibold text-slate-900">${{ number_format($charge->amount, 2) }}</span>

            @if($canEdit)
                <button type="button"
                        wire:click="removeCharge({{ $charge->id }})"
                        class="shrink-0 text-xs font-medium text-red-600 hover:text-red-700">
                    {{ __('Remove') }}
                </button>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-400">{{ __('No extra charges recorded.') }}</p>
    @endforelse

    @if($canEdit)
        <div class="flex items-end gap-2 pt-1">
            <div class="min-w-0 flex-1">
                <label for="charge-description-{{ $log->id }}" class="sr-only">{{ __('Description') }}</label>
                <input type="text"
                       id="charge-description-{{ $log->id }}"
                       wire:model="description"
                       placeholder="{{ __('e.g. Equipment rental') }}"
                       class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-orange-400 focus:ring-orange-200 text-slate-900">
            </div>

            <div class="w-28 shrink-0">
                <label for="charge-amount-{{ $log->id }}" class="sr-only">{{ __('Amount') }}</label>
                <input type="number"
                       id="charge-amount-{{ $log->id }}"
                       wire:model="amount"
                       step="0.01"
                       min="0"
                       placeholder="0.00"
                       class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-orange-400 focus:ring-orange-200">
            </div>

            <button type="button"
                    wire:click="addCharge"
                    class="shrink-0 rounded-full bg-orange-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                {{ __('Add') }}
            </button>
        </div>

        @error('description')
            <p class="text-xs font-semibold text-red-500">{{ $message }}</p>
        @enderror
        @error('amount')
            <p class="text-xs font-semibold text-red-500">{{ $message }}</p>
        @enderror
    @endif
</div>
