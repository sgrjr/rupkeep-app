@props(['active'])

@php
$classes = ($active ?? false)
    ? 'inline-flex items-center rounded-full px-3 py-2 text-sm font-semibold text-orange-600 bg-white shadow-sm ring-1 ring-white/60 transition duration-200 ease-in-out'
    : 'inline-flex items-center rounded-full px-3 py-2 text-sm font-medium text-white/85 hover:text-white hover:bg-white/15 transition duration-200 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
