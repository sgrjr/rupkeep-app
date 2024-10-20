@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active-nav-link inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out'
            : 'nav-link inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
