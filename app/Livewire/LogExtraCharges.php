<?php

namespace App\Livewire;

use App\Models\Invoice;
use App\Models\LogExtraCharge;
use App\Models\UserLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Named ad-hoc charges on a driver log (TASK-330).
 *
 * Mounted in three places -- the log editor, each log card on the job view, and
 * the invoice edit screen -- but there is only ever one destination: rows on the
 * log. The other two screens are convenience paths, which is what keeps a
 * rebuilt invoice reproducing exactly the same charges.
 *
 * Writes immediately rather than staging behind the log's save button, matching
 * how the other child collection on a log already behaves
 * ({@see EditUserLog::uploadFile()}).
 */
class LogExtraCharges extends Component
{
    use AuthorizesRequests;

    public UserLog $log;

    /**
     * Set when mounted from the invoice edit screen. An invoice is a frozen
     * snapshot, so adding a charge has to move that snapshot too or the charge
     * would not appear on the very invoice being edited.
     */
    public ?Invoice $invoice = null;

    public string $description = '';

    public $amount = '';

    public function mount(UserLog $log, ?Invoice $invoice = null): void
    {
        $this->log = $log;
        $this->invoice = $invoice;
    }

    public function addCharge(): void
    {
        $this->authorize('update', $this->log);

        $validated = $this->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ], [], [
            'description' => 'description',
            'amount' => 'amount',
        ]);

        LogExtraCharge::create([
            'user_log_id' => $this->log->id,
            'organization_id' => $this->log->organization_id,
            'description' => trim($validated['description']),
            'amount' => (float) $validated['amount'],
            'sort_order' => ((int) $this->log->extraCharges()->max('sort_order')) + 1,
        ]);

        $this->description = '';
        $this->amount = '';
        $this->resetValidation();

        $this->afterChange();
    }

    public function removeCharge(int $chargeId): void
    {
        $this->authorize('update', $this->log);

        // Scoped through the relation so a charge id from another log -- or
        // another organization -- cannot be deleted by guessing.
        $this->log->extraCharges()->whereKey($chargeId)->delete();

        $this->afterChange();
    }

    /**
     * Re-read the log, and re-point the invoice snapshot at the new totals when
     * this component is being used from the invoice screen.
     */
    protected function afterChange(): void
    {
        $this->log->refresh();
        $this->log->load('extraCharges');

        $this->syncInvoiceSnapshot();

        // Let the host page (job view / log editor) redraw its own totals.
        $this->dispatch('extra-charges-updated', logId: $this->log->id);
    }

    /**
     * Move the three figures that have to travel together.
     *
     * The invoice template derives "Pilot Car Service" as
     * total - everything itemized. Adding a charge to the itemized side without
     * raising the total silently takes that money OUT of the service line and
     * never bills it -- the same class of bug as TASK-365 and TASK-367. So the
     * array, the scalar and the total all move here or none of them do.
     */
    protected function syncInvoiceSnapshot(): void
    {
        if (! $this->invoice) {
            return;
        }

        $job = $this->log->job;

        if (! $job || (int) $this->invoice->pilot_car_job_id !== (int) $job->id) {
            return;
        }

        $logs = $job->logs()->with('extraCharges')->get();

        $values = $this->invoice->values ?? [];
        $previous = (float) ($values['extra_charge'] ?? 0);
        $current = (float) $job->getExtraCharges($logs);

        $values['extra_charges'] = $job->getExtraChargeLines($logs);
        $values['extra_charge'] = number_format($current, 2, '.', '');
        $values['total'] = (float) ($values['total'] ?? 0) + ($current - $previous);

        $this->invoice->values = $values;
        $this->invoice->save();
    }

    public function render()
    {
        return view('livewire.log-extra-charges', [
            'charges' => $this->log->extraCharges,
            'canEdit' => auth()->user()?->can('update', $this->log) ?? false,
        ]);
    }
}
