<?php

namespace Tests\Fixtures;

use Livewire\Component;

/**
 * A component that does nothing but flash, so ActionFeedbackTest can pin
 * down the flash-to-toast bridge (TASK-460) without a real screen's noise.
 */
class FlashingComponent extends Component
{
    public function flashOnly(): void
    {
        session()->flash('success', 'Saved the thing.');
    }

    public function flashStructured(): void
    {
        session()->flash('message', ['warning' => 'Careful now.', 'success' => 'But it worked.']);
    }

    public function flashAndRedirect()
    {
        session()->flash('success', 'Off we go.');

        return redirect()->route('customers.index');
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            <p data-test="inline-flash">{{ session('success') ?? 'no flash' }}</p>
        </div>
        HTML;
    }
}
