<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Vehicle;
use App\Models\UserLog;
use App\Models\PilotCarJob as Job;
use App\Models\User;
use App\Models\Attachment;
use Livewire\WithFileUploads;
use App\Actions\SendUserNotification;
use App\Actions\SendCustomerContactNotification;
use App\Events\InvoiceReady;
use App\Events\JobWasUncanceled;
use App\Models\Invoice;
use App\Models\JobInvoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\CustomerContact;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;

class JobAssignmentForm extends Form
{
    #[Validate('required|exists:users,id|max:255')]
    public $car_driver_id = null;
 
    #[Validate('nullable|exists:vehicles,id|max:255')]
    public $vehicle_id = null;

    #[Validate('required|string')]
    public $vehicle_position = null;
}

#[Layout('layouts.app')]
class ShowPilotCarJob extends Component
{
    use WithFileUploads, AuthorizesRequests;

    public JobAssignmentForm $assignment;
    public $job;
    public ?int $recentInvoiceId = null;

    public $vehicles = [];
    public $drivers = [];
    public $vehicle_positions = [];
    public $file;
    public $trashedLogs = [];

    public function boot()
    {
        // Let Livewire handle Form initialization automatically
    }

    protected function loadJobRelations(): void
    {
        $this->job->load([
            'logs',
            'logs.vehicle',
            'logs.truck_driver',
            'logs.user',
            'logs.attachments',
            'customer',
            'customer.contacts',
            'defaultDriver',
            'defaultTruckDriver',
            'singleInvoices' => fn ($query) => $query->with('children')->latest(),
            'summaryInvoices' => fn ($query) => $query->with('children')->latest(),
        ]);

        $this->job->append('invoices_count');
    }

    public function mount(Int $job){
        // Check for soft-deleted jobs
        $this->job = Job::withTrashed()->find($job);

        if(!$this->job) return redirect()->route('my.jobs.index');

        $this->vehicles = [
            ['name' => '(none selected)', 'value'=>null]
        ];
        
        Vehicle::where('organization_id', $this->job->organization_id)->get()->map(fn($v)=>$this->vehicles[] = ['name'=>$v->name, 'value'=> $v->id ]);

        $this->drivers = [
            ['name' => '(none selected)', 'value'=>null]
        ];
        
        $this->vehicle_positions = Vehicle::positionOptions();

        User::where('organization_id', $this->job->organization_id)->get()->map(fn($d)=>$this->drivers[] = ['name'=>$d->name, 'value'=> $d->id ]);

        $this->authorize('view', $this->job);

        $this->loadJobRelations();
    }

    protected $listeners = ['job-canceled' => 'refreshJob'];

    public function refreshJob($jobId)
    {
        if ($this->job->id == $jobId) {
            $this->job->refresh();
            $this->loadJobRelations();
        }
    }

    public function restoreJob()
    {
        if (!$this->job->trashed()) {
            session()->flash('error', 'Job is not deleted.');
            return;
        }

        $this->authorize('restore', $this->job);
        
        $this->job->restore();
        $this->job->refresh();
        $this->loadJobRelations();
        
        session()->flash('message', 'Job restored successfully.');
    }

    public function restoreLog($logId)
    {
        $log = UserLog::withTrashed()->find($logId);
        
        if (!$log) {
            session()->flash('error', 'Log not found.');
            return;
        }
        
        if (!$log->trashed()) {
            session()->flash('error', 'Log is not deleted.');
            return;
        }

        $this->authorize('restore', $log);
        
        $log->restore();
        $this->job->refresh();
        $this->loadJobRelations();
        
        // Reload trashed logs
        $this->trashedLogs = UserLog::withTrashed()
            ->where('job_id', $this->job->id)
            ->whereNotNull('deleted_at')
            ->with(['vehicle', 'truck_driver', 'user', 'attachments'])
            ->orderBy('deleted_at', 'desc')
            ->get();
        
        session()->flash('message', 'Log restored successfully.');
    }

    public function render()
    {
        return view('livewire.show-pilot-car-job');
    }

    public function assignJob(){
        try {
            // Validate the form - this will throw ValidationException if it fails
            $this->assignment->validate();

            // Ensure we have required values
            if (empty($this->assignment->car_driver_id)) {
                session()->flash('error', __('Please select a driver.'));
                return;
            }

            if (empty($this->assignment->vehicle_position)) {
                session()->flash('error', __('Please select a vehicle position.'));
                return;
            }

            $values = [
                'car_driver_id' => $this->assignment->car_driver_id,
                'job_id' => $this->job->id,
                'vehicle_id' => $this->assignment->vehicle_id ?: null,
                'organization_id' => $this->job->organization_id,
                'vehicle_position' => $this->assignment->vehicle_position,
                'truck_driver_id' => $this->job->default_truck_driver_id ?: null,
                'approval_status' => 'pending', // New logs require approval before editing
            ];

            $message = "New job assignment [{$values['vehicle_position']} car] for {$this->job->customer->name}. Job NO. {$this->job->job_no} | Load NO. {$this->job->load_no}. Pickup: {$this->job->pickup_address} @{$this->job->scheduled_pickup_at} {$this->job->memo}. For updates https://rupkeep.com/my/jobs/{$this->job->id}";

            $new_log = UserLog::create($values);
            
            if ($new_log->user) {
                SendUserNotification::to($new_log->user, $message, subject: 'New Job');
            }

            // Reload job relations to show the new log
            $this->job->refresh();
            $this->loadJobRelations();

            // Reset the form
            $this->assignment->reset();
            
            session()->flash('success', __('Driver and vehicle assigned successfully.'));
            
            $this->dispatch('updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            session()->flash('error', __('An error occurred while assigning the driver: ') . $e->getMessage());
        }
    }

    public $isPublicUpload = false;

    public function uploadFile()
    {
        $this->validate([
            'file' => 'required|file|max:10240',
        ]);

        $originalName = $this->file->getClientOriginalName();
        $this->file->storeAs(path: 'jobs/attachments_'.$this->job->id, name:$originalName);

        Attachment::create([
            'attachable_id' => $this->job->id,
            'attachable_type' => $this->job::class,
            'location' => storage_path('app/private/jobs/attachments_'.$this->job->id.'/'.$originalName),
            'organization_id' => $this->job->organization_id,
            'is_public' => $this->isPublicUpload,
        ]);

        $this->isPublicUpload = false;
        $this->dispatch('uploaded');
        
        return back();
    }

    public function generateInvoice(){
        // Create single invoice for this job (no pivot entry needed)
        $invoice = $this->job->createInvoice();

        $invoice->refresh();

        event(new InvoiceReady($invoice));

        $this->recentInvoiceId = $invoice->id;

        $this->loadJobRelations();

        session()->flash('success', __('Invoice #:number created for job :job.', [
            'number' => $invoice->invoice_number,
            'job' => $this->job->job_no ?? $this->job->id,
        ]));

        $this->dispatch('updated');
    }

    /**
     * Send notification to a customer contact (truck driver)
     */
    public function notifyCustomerContact(int $contactId): void
    {
        $contact = CustomerContact::findOrFail($contactId);
        
        // Verify contact belongs to this job's customer
        if ($contact->customer_id !== $this->job->customer_id) {
            session()->flash('error', __('Contact does not belong to this job\'s customer.'));
            return;
        }

        // Build job status message
        $status = $this->job->status_label;
        $scheduledAt = $this->job->scheduled_pickup_at 
            ? \Carbon\Carbon::parse($this->job->scheduled_pickup_at)->toDayDateTimeString()
            : 'Not scheduled';
        
        $message = sprintf(
            "Hello %s,\n\nJob Status Update for %s\n\nJob Details:\n- Job #: %s\n- Load #: %s\n- Status: %s\n- Pickup: %s\n- Delivery: %s\n- Scheduled Pickup: %s\n\nFor updates, contact your dispatcher.",
            $contact->name,
            $this->job->customer->name,
            $this->job->job_no ?? ('#'.$this->job->id),
            $this->job->load_no ?: 'Not provided',
            $status,
            $this->job->pickup_address ?: 'Not yet provided',
            $this->job->delivery_address ?: 'Not yet provided',
            $scheduledAt
        );

        $subject = sprintf('Job Status: %s - %s', $status, $this->job->job_no ?? ('Job '.$this->job->id));

        SendCustomerContactNotification::to($contact, $message, $subject);
        
        session()->flash('success', __('Notification sent to ') . $contact->name);
    }

    /**
     * Delete an invoice from the job show page.
     */
    public function deleteInvoice(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $this->authorize('delete', $invoice);

        // If this is a summary invoice, release children first
        if ($invoice->isSummary()) {
            foreach ($invoice->children as $child) {
                $child->update(['parent_invoice_id' => null]);
            }
            JobInvoice::where('invoice_id', $invoice->id)->delete();
        }

        $invoice->forceDelete();
        $this->loadJobRelations();

        session()->flash('success', __('Invoice deleted.'));
    }

    /**
     * Reverse job cancellation
     */
    public function uncancelJob(): void
    {
        $this->authorize('update', $this->job);

        if (!$this->job->canceled_at) {
            session()->flash('error', __('This job is not canceled.'));
            return;
        }

        $updateData = [
            'canceled_at' => null,
            'canceled_reason' => null,
        ];

        // If the rate_code was set to a cancellation type, reset it to null
        $cancellationRateCodes = ['show_no_go', 'cancellation_24hr', 'cancel_without_billing'];
        if (in_array($this->job->rate_code, $cancellationRateCodes)) {
            $updateData['rate_code'] = null;
            $updateData['rate_value'] = null;
        }

        // Store previous cancellation reason before updating
        $previousReason = $this->job->canceled_reason;

        $this->job->update($updateData);
        $this->job->refresh();

        // Fire the JobWasUncanceled event
        event(new JobWasUncanceled(
            $this->job,
            $previousReason
        ));

        $this->loadJobRelations();

        session()->flash('success', __('Job cancellation has been reversed. The job is now active again.'));
    }
}
