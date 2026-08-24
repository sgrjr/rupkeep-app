<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use App\Events\LogCompleted;
use App\Models\User;
use App\Models\UserLog;
use App\Models\PilotCarJob;
use App\Models\Vehicle; // Corrected typo here
use App\Models\CustomerContact;
use App\Models\Attachment;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EditLogForm extends Form
{
    #[Validate('exists:pilot_car_jobs,id|min:1')]
    public $job_id = null;

    #[Validate('nullable|exists:users,id|min:1')]
    public $car_driver_id = null;

    #[Validate('nullable|exists:customer_contacts,id|min:1')]
    public $truck_driver_id = null;
    public $new_truck_driver_name = null;
    public $new_truck_driver_phone = null;
    public $new_truck_driver_memo = null;
    public $vehicle_id = null;
    public $vehicle_position = null;

    public $truck_no = null;

    public $trailer_no = null;

    public $start_mileage = null;

    public $end_mileage = null;

    public $start_job_mileage = null;

    public $end_job_mileage = null;

    #[Validate('nullable|numeric|min:0')]
    public $billable_miles = null;

    // These are booleans from the form, will convert to 0/1 for DB
    #[Validate('nullable|boolean')]
    public $load_canceled = false;

    public $extra_charge = null;

    // Deadhead, as two explicit quantities rather than the old is_deadhead
    // boolean (TASK-354). `driven` is measurement and is recorded whether or
    // not a cent of it is charged; `billed` is the money decision, opt-in and
    // capped by published policy. The ceiling is enforced in saveLog(), not
    // here, because it depends on the organization's own free-miles setting.
    #[Validate('nullable|numeric|min:0')]
    public $dead_head_driven = null;

    #[Validate('nullable|numeric|min:0')]
    public $dead_head_billed = null;

    public $extra_load_stops_count = null;

    public $wait_time_hours = null;

    public $tolls = null;

    public $gas = null;

    public $hotel = null;

    public $memo = null;

    // The related PilotCarJob's customer-facing memo (job_public_memo, TASK-091).
    // Not a UserLog column — saveLog() persists it onto $this->log->job separately.
    #[Validate('nullable|string|max:1000')]
    public $job_public_memo = null;

    // This is a boolean from the form, will convert to 0/1 for DB
    #[Validate('nullable|boolean')]
    public $pretrip_check = false;

    public $maintenance_memo = null;

    public $started_at = null;

    public $ended_at = null;

    public $clock_in = null;

    public $clock_out = null;
}

class EditUserLog extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public EditLogForm $form;

    public $rates = [];
    public $car_drivers = [];
    public $vehicles = [];
    public $customer_contacts = [];

    public $vehicle_positions = [];

    public UserLog $log; // Holds the UserLog model instance, now type-hinted

    public $file; // For file uploads

    // Properties to manage the open/closed state of details sections
    public $isDriverVehicleOpen = false; // Default to open
    public $isTripTimingOpen = false;
    public $isJobDetailsOpen = false;
    public $isExpenseDetailsOpen = false;
    public $isLoadInformationOpen = false;
    public $isAttachmentsOpen = false;


    protected $listeners = [
        'saved' => '$refresh',
    ];

    public function openAllSections()
    {
        $this->isDriverVehicleOpen = true;
        $this->isTripTimingOpen = true;
        $this->isJobDetailsOpen = true;
        $this->isExpenseDetailsOpen = true;
        $this->isLoadInformationOpen = true;
        $this->isAttachmentsOpen = true;
    }

    public function closeAllSections()
    {
        $this->isDriverVehicleOpen = false;
        $this->isTripTimingOpen = false;
        $this->isJobDetailsOpen = false;
        $this->isExpenseDetailsOpen = false;
        $this->isLoadInformationOpen = false;
        $this->isAttachmentsOpen = false;
    }

    public function confirmLog()
    {
        $this->authorize('confirm', $this->log);
        
        $this->log->update([
            'approval_status' => 'confirmed',
            'approved_at' => now(),
            'approved_by_id' => Auth::id(),
        ]);
        
        $this->log->refresh();
        session()->flash('success', __('Log confirmed successfully.'));
        $this->dispatch('updated');
    }

    public function denyLog()
    {
        $this->authorize('deny', $this->log);
        
        $this->log->update([
            'approval_status' => 'denied',
            'approved_at' => now(),
            'approved_by_id' => Auth::id(),
        ]);
        
        $this->log->refresh();
        session()->flash('success', __('Log denied.'));
        $this->dispatch('updated');
    }

    /**
     * The driver's "I'm done" signal (TASK-364). Saving a log only recorded
     * data; nothing told the office the job was ready to review and bill, and
     * job status was derived solely from whether an invoice already existed.
     */
    public function markComplete()
    {
        $this->authorize('complete', $this->log);

        if ($this->log->approval_status === 'denied') {
            session()->flash('error', __('This log has been denied and cannot be completed.'));
            return;
        }

        if ($this->log->isComplete()) {
            return; // Already handed off - don't re-notify the office.
        }

        // Save before handing off (TASK-399). This used to write only the
        // completion stamp, so a driver who filled in the log and clicked this
        // without pressing Save first handed the office a log marked complete
        // and empty -- their typed values lived in component state, which is
        // not the database, and were gone on the next page load.
        if (! $this->saveLog()) {
            return;
        }

        $this->log->update([
            'completed_at' => now(),
            'completed_by_id' => Auth::id(),
        ]);

        $this->log->refresh();

        LogCompleted::dispatch($this->log, Auth::user());

        session()->flash('success', __('Log marked complete. The office has been notified.'));
        $this->dispatch('updated');
    }

    /**
     * Managers only - see UserLogPolicy::reopen().
     */
    public function reopenLog()
    {
        $this->authorize('reopen', $this->log);

        $this->log->update([
            'completed_at' => null,
            'completed_by_id' => null,
        ]);

        $this->log->refresh();
        session()->flash('success', __('Log reopened for edits.'));
        $this->dispatch('updated');
    }

    public function mount(UserLog $log)
    {
        $this->log = $log->load('organization', 'job', 'job.customer', 'job.attachments', 'attachments', 'approvedBy', 'completedBy');

        // Check if log requires approval before editing
        if ($this->log->approval_status === 'pending' && $this->log->car_driver_id && auth()->user()->id === $this->log->car_driver_id) {
            // Assigned driver must confirm/deny before editing - don't authorize update yet
            // They'll see the approval UI instead
        } elseif ($this->log->approval_status === 'denied') {
            // Denied logs cannot be edited, but the tenant/ownership boundary
            // must still hold (TASK-357). Previously this branch skipped
            // authorization entirely, so ANY authenticated user from ANY org
            // could open a denied log and read its driver, mileage and expense
            // detail. Authorize 'view' instead: a same-org member (including the
            // assigned driver) can still read their own denied log, while a
            // cross-tenant user is refused.
            $this->authorize('view', $this->log);
        } else {
            // Normal authorization for confirmed logs or managers/admins
            $this->authorize('update', $this->log);
        }

        $this->car_drivers = [['name' => '(none selected)', 'value' => null]];
        User::where('organization_id', $this->log->organization_id)->get()->each(function ($user) {
            $this->car_drivers[] = ['name' => $user->name, 'value' => $user->id];
        });

        $this->vehicles = [['name' => '(none selected)', 'value' => null]];
        Vehicle::where('organization_id', $this->log->organization_id)->get()->each(function ($v) {
            $this->vehicles[] = ['name' => $v->name, 'value' => $v->id];
        });

        $this->customer_contacts = [['name' => '(none selected)', 'value' => null]];
        CustomerContact::where('customer_id', $this->log->job->customer_id)->get()->each(function ($c) {
            $this->customer_contacts[] = ['name' => $c->name . ' (' . $c->phone . ')', 'value' => $c->id];
        });

        $this->vehicle_positions = Vehicle::positionOptions();

        $this->form->fill([
            'job_id' => $this->log->job_id,
            'car_driver_id' => $this->log->car_driver_id,
            'truck_driver_id' => $this->log->truck_driver_id,
            'vehicle_id' => $this->log->vehicle_id,
            'vehicle_position' => $this->log->vehicle_position,
            'truck_no' => $this->log->truck_no,
            'trailer_no' => $this->log->trailer_no,
            'start_mileage' => $this->log->start_mileage,
            'end_mileage' => $this->log->end_mileage,
            'start_job_mileage' => $this->log->start_job_mileage,
            'end_job_mileage' => $this->log->end_job_mileage,
            'billable_miles' => $this->log->billable_miles,
            'load_canceled' => (bool)$this->log->load_canceled,
            'extra_charge' => $this->log->extra_charge,
            // Seed the driven miles from the odometer's own approach leg when
            // nothing is stored yet: start_mileage -> start_job_mileage IS the
            // drive to the pickup, so the number is already known and the
            // driver only has to correct it when the readings are off.
            'dead_head_driven' => $this->log->dead_head_driven ?? $this->log->suggestedDeadHeadMiles(),
            'dead_head_billed' => $this->log->dead_head_billed,
            'extra_load_stops_count' => $this->log->extra_load_stops_count,
            'wait_time_hours' => $this->log->wait_time_hours,
            'tolls' => $this->log->tolls,
            'gas' => $this->log->gas,
            'hotel' => $this->log->hotel,
            'memo' => $this->log->memo,
            'job_public_memo' => $this->log->job?->public_memo,
            'pretrip_check' => (bool)$this->log->pretrip_check,
            'maintenance_memo' => $this->log->maintenance_memo,
            // <input type="datetime-local"> requires "Y-m-d\TH:i". Carbon's default
            // toString format ("Y-m-d H:i:s") is rejected by the browser as an
            // invalid value, leaving the field visually empty even when the DB
            // has data. clock_in/clock_out are cast to datetime on the model so
            // they arrive as Carbon; started_at/ended_at are NOT cast so they
            // arrive as strings — Carbon::parse handles both.
            'started_at' => $this->log->started_at ? \Carbon\Carbon::parse($this->log->started_at)->format('Y-m-d\TH:i') : null,
            'ended_at' => $this->log->ended_at ? \Carbon\Carbon::parse($this->log->ended_at)->format('Y-m-d\TH:i') : null,
            'clock_in' => $this->log->clock_in ? \Carbon\Carbon::parse($this->log->clock_in)->format('Y-m-d\TH:i') : null,
            'clock_out' => $this->log->clock_out ? \Carbon\Carbon::parse($this->log->clock_out)->format('Y-m-d\TH:i') : null,
            'new_truck_driver_name' => null,
            'new_truck_driver_phone' => null,
            'new_truck_driver_memo' => null,
        ]);
        
    }

    public function render()
    {
        return view('livewire.edit-user-log');
    }

    /**
     * Persist the form onto the log.
     *
     * Returns whether the work was actually written, so callers that do
     * something irreversible afterwards -- markComplete() hands the log to the
     * office -- can refuse to proceed on a refused save (TASK-399).
     */
    public function saveLog(): bool
    {
        // Prevent saving if log is pending approval and user is the assigned driver
        if ($this->log->approval_status === 'pending' && $this->log->car_driver_id && auth()->user()->id === $this->log->car_driver_id) {
            session()->flash('error', __('Please confirm or deny this log assignment before editing.'));
            return false;
        }

        // A completed log has been handed to the office for review (TASK-364).
        // The driver cannot keep editing underneath that review; a manager can,
        // since they are the ones doing the reviewing.
        if ($this->log->isComplete() && ! auth()->user()->can('reopen', $this->log)) {
            session()->flash('error', __('This log is marked complete. Ask a manager to reopen it if it needs changes.'));
            return false;
        }
        
        // Prevent saving if log is denied
        if ($this->log->approval_status === 'denied') {
            session()->flash('error', __('This log has been denied and cannot be edited.'));
            return false;
        }
        
        try {
            $this->form->validate();

            // The published free allowance is a ceiling, not a suggestion
            // (TASK-354). Billing beyond driven - free_miles would charge for
            // miles the price sheet promises are free, which would make the
            // invoice contradict the quote the customer was given.
            if ((float) ($this->form->dead_head_billed ?? 0) > $this->deadHeadCeiling() + 0.001) {
                $this->addError('form.dead_head_billed', __('At most :max deadhead miles can be billed here: :driven driven, less the first :free free.', [
                    'max' => rtrim(rtrim(number_format($this->deadHeadCeiling(), 2), '0'), '.'),
                    'driven' => rtrim(rtrim(number_format((float) ($this->form->dead_head_driven ?? 0), 2), '0'), '.'),
                    'free' => rtrim(rtrim(number_format($this->log->deadHeadFreeMiles(), 2), '0'), '.'),
                ]));

                return false;
            }

            if (!empty($this->form->new_truck_driver_name)) {
                $truck_driver_data = [
                    'name' => $this->form->new_truck_driver_name,
                    'customer_id' => $this->log->job->customer_id,
                    'phone' => $this->form->new_truck_driver_phone,
                    'memo' => $this->form->new_truck_driver_memo,
                    'organization_id' => $this->log->organization_id,
                ];

                $existing = CustomerContact::where('name', $truck_driver_data['name'])
                                            ->where('customer_id', $truck_driver_data['customer_id'])
                                            ->where('phone', $truck_driver_data['phone'])
                                            ->where('organization_id', $truck_driver_data['organization_id'])
                                            ->first();

                if ($existing) {
                    if (!empty($truck_driver_data['memo'])) {
                        $existing->update(['memo' => $truck_driver_data['memo']]);
                    }
                    $this->form->truck_driver_id = $existing->id;
                } else {
                    $newTruckDriver = CustomerContact::create($truck_driver_data);
                    $this->form->truck_driver_id = $newTruckDriver->id;
                }

                $this->customer_contacts = [['name' => '(none selected)', 'value' => null]];
                CustomerContact::where('customer_id', $this->log->job->customer_id)->get()->each(function ($c) {
                    $this->customer_contacts[] = ['name' => $c->name . ' (' . $c->phone . ')', 'value' => $c->id];
                });

                $this->form->new_truck_driver_name = null;
                $this->form->new_truck_driver_phone = null;
                $this->form->new_truck_driver_memo = null;
            }

            $updateData = $this->form->except([
                'job_id',
                'new_truck_driver_name',
                'new_truck_driver_phone',
                'new_truck_driver_memo',
                'job_public_memo', // belongs to PilotCarJob, not UserLog — persisted separately below
            ]);

            $updateData['load_canceled'] = $this->form->load_canceled ? 1 : 0;
            $updateData['pretrip_check'] = $this->form->pretrip_check ? 1 : 0;

            // extra_load_stops_count is a NOT NULL integer (default 0); an empty
            // field arrives as null and would fail the constraint, silently
            // aborting the whole save (TASK-318). A missing count means zero.
            $updateData['extra_load_stops_count'] = (int) ($this->form->extra_load_stops_count ?? 0);

            foreach (['billable_miles', 'dead_head_driven', 'dead_head_billed'] as $mileField) {
                if (array_key_exists($mileField, $updateData)) {
                    $updateData[$mileField] = $this->normalizeMiles($updateData[$mileField]);
                }
            }

            $this->log->update($updateData);

            // TASK-091: the Job's customer-facing memo is edited here but lives
            // on the related PilotCarJob, not the UserLog — guard for a log
            // whose job was deleted/detached.
            if ($this->log->job) {
                $this->log->job->update(['public_memo' => $this->form->job_public_memo]);
            }

            $this->dispatch('saved');

            return true;

        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Please correct the validation errors below.');
            throw $e;
        } catch (\Exception $e) {
            // Never swallow a save failure silently — that hid TASK-318 for a
            // long time. Log it so the failure is diagnosable, then surface it.
            \Log::error('EditUserLog: log save failed', [
                'log_id' => $this->log->id ?? null,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', 'An unexpected error occurred while saving: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Billable miles as they stand right now, derived from what is typed in
     * the form rather than from the saved model (TASK-397).
     *
     * This used to be a property assigned once in mount(), so it displayed the
     * state at page load forever -- a log that was empty when opened read
     * 0.0 no matter what was entered or saved. The blade's `??` fallback to the
     * live accessor could never rescue it either, because the property was
     * initialised to 0 and so was never null.
     *
     * Mirrors the precedence in UserLog::getTotalBillableMilesAttribute() so
     * the figure on screen and the figure on the invoice cannot disagree.
     */
    #[Computed]
    public function calculatedBillableMiles(): float
    {
        $jobSpan = $this->spanFromForm('start_job_mileage', 'end_job_mileage');

        if ($jobSpan !== null) {
            return $jobSpan;
        }

        return $this->spanFromForm('start_mileage', 'end_mileage') ?? 0.0;
    }

    /**
     * Total miles as typed, for the section summary line.
     */
    #[Computed]
    public function totalMilesFromForm(): float
    {
        return $this->spanFromForm('start_mileage', 'end_mileage') ?? 0.0;
    }

    /**
     * The approach leg described by the odometer values currently in the form.
     *
     * Deliberately reads the form, not the saved log: a driver entering their
     * mileage for the first time needs the suggestion immediately, not after a
     * save and reload (TASK-398).
     */
    public function approachMilesFromForm(): ?float
    {
        $approach = $this->spanFromForm('start_mileage', 'start_job_mileage');

        if ($approach === null || $approach <= 0 || $approach > UserLog::MAX_PLAUSIBLE_APPROACH) {
            return null;
        }

        return $approach;
    }

    /**
     * A forward span between two odometer fields, or null when the pair cannot
     * describe one.
     */
    private function spanFromForm(string $from, string $to): ?float
    {
        $start = $this->form->{$from};
        $end = $this->form->{$to};

        if (! is_numeric($start) || ! is_numeric($end)) {
            return null;
        }

        $span = (float) $end - (float) $start;

        return $span >= 0 ? $span : null;
    }

    /**
     * Offer the odometer's approach the moment the readings describe one, but
     * never overwrite a figure a person has already put in the field
     * (TASK-398). Without this the suggestion only ever appeared on a log that
     * already had mileage when the page loaded, which a freshly assigned log
     * never does.
     */
    public function updated(string $property): void
    {
        $odometerFields = [
            'form.start_mileage',
            'form.start_job_mileage',
            'form.end_job_mileage',
            'form.end_mileage',
        ];

        if (! in_array($property, $odometerFields, true)) {
            return;
        }

        if ($this->form->dead_head_driven === null || $this->form->dead_head_driven === '') {
            $this->form->dead_head_driven = $this->approachMilesFromForm();
        }
    }

    /**
     * The most this log may bill for deadhead, recomputed from whatever is     * currently typed in the driven field so the form can show the ceiling
     * live rather than only rejecting an over-entry after the fact.
     */
    public function deadHeadCeiling(): float
    {
        return max(0.0, (float) ($this->form->dead_head_driven ?? 0) - $this->log->deadHeadFreeMiles());
    }

    protected function normalizeMiles($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9\.\-]/', '', (string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public $isPublicUpload = false;

    public function uploadFile()
    {
        $this->validate([
            'file' => 'required|file|max:10240',
        ]);

        try {
            $originalName = $this->file->getClientOriginalName();
            $path = 'jobs/attachments_' . $this->log->job_id;
            $this->file->storeAs(path: $path, name: $originalName, disk: 'private');

            Attachment::create([
                'attachable_id' => $this->log->id,
                'attachable_type' => get_class($this->log),
                'location' => $path . '/' . $originalName,
                'file_name' => $originalName,
                'organization_id' => $this->log->organization_id,
                'is_public' => $this->isPublicUpload,
            ]);

            $this->isPublicUpload = false;
            $this->dispatch('uploaded');
            $this->log->refresh();
            $this->dispatch('$refresh');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to upload file: ' . $e->getMessage());
        }
    }
}
