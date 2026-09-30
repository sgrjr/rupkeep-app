<?php

namespace App\Support;

use Livewire\Component;

use function Livewire\on;

/**
 * Make a session flash raised inside a Livewire request reach the screen
 * (TASK-460).
 *
 * The layout's toast stack reads session('success' | 'error' | ...) once, at
 * full page load. A Livewire action runs in its own request and re-renders
 * only its component, so a flash raised there was never shown -- and, since a
 * flash lives until the *next* request, it then turned up on whatever page
 * the person opened afterwards, out of context.
 *
 * This hook runs just before a component renders in a Livewire request. Any
 * flash set during this request is dispatched as a `notify` browser event,
 * which the toast stack listens for on the window, and then demoted to the
 * current request only, so it still feeds an in-component
 * `@if(session('error'))` block but does not leak to the next page.
 *
 * A redirecting action skips render, so its flash is left alone and the
 * layout shows it after navigation, exactly as before.
 */
class LivewireFlashToasts
{
    /** The flash keys both layouts read. */
    public const CHANNELS = ['success', 'error', 'warning', 'info', 'message'];

    public static function register(): void
    {
        on('render', function (Component $component) {
            if (! app('livewire')->isLivewireRequest()) {
                return; // Initial page load: the layout renders the session itself.
            }

            static::hoist($component);
        });
    }

    /**
     * Dispatch this request's fresh flashes from the component and keep them
     * from surviving into the next request.
     */
    public static function hoist(Component $component): void
    {
        $session = session();

        $fresh = array_values(array_intersect((array) $session->get('_flash.new', []), static::CHANNELS));

        foreach ($fresh as $key) {
            $value = $session->get($key);

            foreach (static::toasts($key, $value) as $toast) {
                $component->dispatch('notify', type: $toast['type'], message: $toast['message']);
            }

            // now() keeps the value for the render that follows and ages it out
            // at the end of this request; the key must also leave the "new"
            // list or ageFlashData() would carry it over anyway.
            $session->now($key, $value);
            $session->put('_flash.new', array_values(array_diff((array) $session->get('_flash.new', []), [$key])));
        }
    }

    /**
     * One flash key's value as toast rows. Mirrors the layout: 'message' is
     * plain info when it is a string, and a type => message map when it is an
     * array.
     *
     * @return array<int, array{type: string, message: string}>
     */
    public static function toasts(string $key, mixed $value): array
    {
        if (is_string($value) || $value instanceof \Stringable) {
            $text = trim((string) $value);

            return $text === '' ? [] : [['type' => $key === 'message' ? 'info' : $key, 'message' => $text]];
        }

        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $type => $message) {
            if (! is_string($message) || trim($message) === '') {
                continue;
            }
            $rows[] = [
                'type' => is_string($type) && in_array($type, ['success', 'error', 'warning', 'info'], true) ? $type : 'info',
                'message' => trim($message),
            ];
        }

        return $rows;
    }
}
