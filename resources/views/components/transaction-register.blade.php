@props(['transactions', 'showBalance' => true, 'accountCredit' => 0])

@php
    $runningBalance = 0;
    // Add account credit as a display line (but don't include it in balance calculation yet)
    $displayTransactions = collect($transactions);
    if (($accountCredit ?? 0) != 0) {
        $displayTransactions->prepend([
            'date' => now()->subYears(1), // Show at the beginning
            'type' => 'credit',
            'amount' => abs($accountCredit ?? 0),
            'description' => __('Account Credit'),
            'reference' => __('Available Credit'),
            'reference_url' => null,
            'sort_order' => 0,
            'is_account_credit' => true, // Flag to exclude from balance calculation
        ]);
    }
    $sortedTransactions = collect($transactions)->map(function($transaction) {
        // Ensure date is a Carbon instance or string that can be parsed
        if (isset($transaction['date']) && !($transaction['date'] instanceof \Carbon\Carbon)) {
            $transaction['date'] = \Carbon\Carbon::parse($transaction['date']);
        }
        return $transaction;
    })->sortBy(function($transaction) {
        // Sort by sort_order first (0 = account credit at top), then by date, then by type
        $sortOrder = $transaction['sort_order'] ?? 1;
        $date = isset($transaction['date']) ? $transaction['date'] : now();
        $typeOrder = ($transaction['type'] ?? '') === 'credit' ? 0 : 1;
        return [$sortOrder, $date->timestamp, $typeOrder];
    });
@endphp

<div class="overflow-hidden rounded-2xl border border-slate-200 flex flex-col">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Reference') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Debit') }}</th>
                    @if($showBalance)
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                    @endif
                </tr>
            </thead>
        </table>
    </div>
    <div class="max-h-[420px] overflow-y-auto overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <tbody class="divide-y divide-slate-100 bg-white">
            @forelse($sortedTransactions as $transaction)
                @php
                    $isCredit = isset($transaction['type']) && $transaction['type'] === 'credit';
                    $isDebit = isset($transaction['type']) && $transaction['type'] === 'debit';
                    $amount = $transaction['amount'] ?? 0;
                    $isAccountCreditLine = ($transaction['is_account_credit'] ?? false) || (($transaction['description'] ?? '') === __('Account Credit'));
                    
                    // In customer account register: Debits increase balance (charges), Credits decrease balance (payments/credits)
                    // Account credit line is for display only - don't include in running balance calculation
                    if (!$isAccountCreditLine) {
                        if ($isDebit) {
                            $runningBalance += $amount; // Debit = charge = increases what customer owes
                        } elseif ($isCredit) {
                            $runningBalance -= $amount; // Credit = payment/credit = decreases what customer owes
                        }
                    }
                @endphp
                <tr class="{{ $isAccountCreditLine ? 'bg-amber-50 font-semibold border-t-2 border-amber-200' : 'hover:bg-slate-50' }}">
                    <td class="px-4 py-3 text-slate-600">
                        @if(isset($transaction['date']))
                            @php
                                $date = $transaction['date'] instanceof \Carbon\Carbon 
                                    ? $transaction['date'] 
                                    : \Carbon\Carbon::parse($transaction['date']);
                            @endphp
                            {{ $date->format('M j, Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3 font-medium text-slate-900">
                        {{ $transaction['description'] ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        @if(isset($transaction['reference_url']))
                            <a href="{{ $transaction['reference_url'] }}" class="text-orange-600 hover:text-orange-700 hover:underline">
                                {{ $transaction['reference'] ?? '—' }}
                            </a>
                        @else
                            {{ $transaction['reference'] ?? '—' }}
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-medium {{ $isCredit ? 'text-emerald-600' : 'text-slate-400' }}">
                        {{ $isCredit ? '$' . number_format($amount, 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-medium {{ $isDebit ? 'text-red-600' : 'text-slate-400' }}">
                        {{ $isDebit ? '$' . number_format($amount, 2) : '—' }}
                    </td>
                    @if($showBalance)
                        <td class="px-4 py-3 text-right font-semibold {{ $runningBalance >= 0 ? 'text-slate-900' : 'text-red-600' }}">
                            @if($isAccountCreditLine)
                                —
                            @else
                                ${{ number_format($runningBalance, 2) }}
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showBalance ? '6' : '5' }}" class="px-4 py-6 text-center text-sm text-slate-400">
                        {{ __('No transactions found.') }}
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($showBalance && $sortedTransactions->isNotEmpty())
        @php
            // Apply account credit to the final balance (account credit reduces what customer owes)
            $finalBalance = $runningBalance - ($accountCredit ?? 0);
        @endphp
        <div class="overflow-x-auto border-t-2 border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <tfoot class="bg-slate-50">
                    @if(($accountCredit ?? 0) != 0)
                        <tr class="border-b border-slate-200">
                            <td colspan="{{ $showBalance ? '5' : '4' }}" class="px-4 py-2 text-right text-sm text-slate-600">
                                {{ __('Account Credit Available') }}:
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-emerald-600">
                                -${{ number_format($accountCredit ?? 0, 2) }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td colspan="{{ $showBalance ? '5' : '4' }}" class="px-4 py-3 text-right font-semibold text-slate-900">
                            {{ __('Amount Due') }}:
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-lg {{ $finalBalance > 0 ? 'text-slate-900' : ($finalBalance < 0 ? 'text-emerald-600' : 'text-slate-600') }}">
                            @if($finalBalance <= 0)
                                $0.00
                                @if($finalBalance < 0)
                                    <span class="text-xs font-normal text-emerald-600 ml-2">({{ __('Credit: $:amount', ['amount' => number_format(abs($finalBalance), 2)]) }})</span>
                                @endif
                            @else
                                ${{ number_format($finalBalance, 2) }}
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
