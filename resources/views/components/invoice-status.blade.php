@props(['invoice', 'size' => 'sm'])
@php
    $classes = match ($invoice->status) {
        \App\Models\Invoice::STATUS_DRAFT => 'border-slate-300 bg-slate-100 text-slate-700',
        \App\Models\Invoice::STATUS_SENT => 'border-blue-200 bg-blue-50 text-blue-700',
        \App\Models\Invoice::STATUS_PAID => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        \App\Models\Invoice::STATUS_VOID => 'border-red-200 bg-red-50 text-red-700 line-through',
        default => 'border-slate-200 bg-white text-slate-600',
    };
    $sizing = $size === 'xs'
        ? 'px-1.5 py-0.5 text-[9px]'
        : 'px-2 py-0.5 text-[11px]';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border font-semibold uppercase tracking-wide {$sizing} {$classes}"]) }}
      title="{{ $invoice->isVoid() && $invoice->voided_at ? __('Voided :date', ['date' => $invoice->voided_at->format('M j, Y')]) : ($invoice->sent_at ? __('Sent :date', ['date' => $invoice->sent_at->format('M j, Y')]) : '') }}">
    {{ $invoice->statusLabel() }}
</span>
