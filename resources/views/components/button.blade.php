@props([
    'variant' => 'primary', // primary, secondary, ghost, danger
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

    $variants = [
        'primary' => 'bg-orange-500 text-white hover:bg-orange-600 focus:ring-orange-200',
        'secondary' => 'border border-orange-200 bg-orange-50 text-orange-600 hover:bg-orange-500 hover:text-white focus:ring-orange-100',
        'ghost' => 'border border-slate-200 bg-white text-slate-700 hover:border-orange-200 hover:text-orange-600 focus:ring-orange-100',
        'danger' => 'bg-red-500 text-white hover:bg-red-600 focus:ring-red-200',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']);
@endphp

<button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
    {{ $slot }}
</button>
