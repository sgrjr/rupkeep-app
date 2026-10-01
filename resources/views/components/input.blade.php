@props(['disabled' => false])

{{-- A 3:1 border, a white field and dark text by default (TASK-463). The
     component used to set only border-gray-300, so ~140 controls inherited
     whatever colour the surrounding theme gave them. --}}
<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-slate-400 bg-white text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-orange-400 dark:focus:border-orange-600 focus:ring-orange-400 dark:focus:ring-orange-600 rounded-md shadow-sm']) !!}>
