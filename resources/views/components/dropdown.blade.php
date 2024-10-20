@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1', 'dropdownClasses' => '', 'active'=>false])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    'none', 'false' => '',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '60' => 'w-60',
    default => 'w-48',
};

$main_classes = ($active ?? false)
            ? 'active-menu-drop-down text-sm font-medium leading-5 transition duration-150 ease-in-out'
            : 'text-sm font-medium leading-5 transition duration-150 ease-in-out';

@endphp

<div class="menu-drop-down relative {{ $main_classes }}" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open" class="inline-flex items-center">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="menu-drop-down-content top-8 left-0 absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }} {{ $dropdownClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>