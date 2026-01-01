<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\PilotCarJob;
use App\Events\JobWasCanceled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class CancelJob extends Component
{
    use AuthorizesRequests;

    public PilotCarJob $job;
    public bool $showModal = false;

    #[Validate('required|string')]
    public string $cancellationReason = '';

    #[Validate('required|string|in:CANCEL,show_no_go,cancellation_24hr,cancel_without_billing')]
    public string $cancellationType = 'CANCEL';

    #[Validate('nullable|string|max:1000')]
    public string $customReason = '';

    protected $listeners = [];

    public function mount(PilotCarJob $job)
    {
        $this->job = $job;
        $this->listeners['open-cancel-job-modal-' . $this->job->id] = 'openModal';
    }

    public function boot()
    {
        $this->listeners['open-cancel-job-modal-' . $this->job->id] = 'openModal';
    }

    public function openModal()
    {
        $this->authorize('update', $this->job);
        $this->showModal = true;
        $this->resetValidation();
        $this->reset(['cancellationReason', 'cancellationType', 'customReason']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetValidation();
        $this->reset(['cancellationReason', 'cancellationType', 'customReason']);
    }

    public function cancel()
    {
        try {
            \Log::info('CancelJob: Starting cancel method', [
                'job_id' => $this->job->id,
                'cancellation_reason' => $this->cancellationReason,
                'cancellation_type' => $this->cancellationType,
                'custom_reason' => $this->customReason,
            ]);

            $this->authorize('update', $this->job);

            \Log::info('CancelJob: Authorization passed, validating', [
                'job_id' => $this->job->id,
            ]);

            $this->validate();

            \Log::info('CancelJob: Validation passed', [
                'job_id' => $this->job->id,
            ]);

            // Determine the actual cancellation type
            $actualCancellationType = $this->cancellationType;
            
            if ($this->cancellationType === 'CANCEL') {
                // Auto-determine based on timing
                $actualCancellationType = $this->job->determineCancellationType();
            }

            // Build the reason text
            $reasonText = $this->cancellationReason;
            if ($this->customReason) {
                $reasonText .= ': ' . $this->customReason;
            }

            // Update the job
            $updateData = [
                'canceled_at' => now(),
                'canceled_reason' => $reasonText,
            ];

            // If a specific cancellation type was selected, update rate_code and rate_value
            if ($actualCancellationType !== 'CANCEL') {
                $pricingConfig = config('pricing.rates.' . $actualCancellationType, []);
                if (!empty($pricingConfig)) {
                    $updateData['rate_code'] = $actualCancellationType;
                    if (isset($pricingConfig['flat_amount'])) {
                        $updateData['rate_value'] = (string) $pricingConfig['flat_amount'];
                    }
                }
            }

            $this->job->update($updateData);

            \Log::info('CancelJob: Job canceled, firing event', [
                'job_id' => $this->job->id,
                'job_no' => $this->job->job_no,
                'cancellation_type' => $actualCancellationType,
                'reason' => $reasonText,
            ]);

            // Fire the JobWasCanceled event
            // Refresh the job model to ensure it's up-to-date before serialization
            $this->job->refresh();
            
            try {
                event(new JobWasCanceled(
                    $this->job,
                    $reasonText,
                    $actualCancellationType
                ));
                
                \Log::info('CancelJob: Event fired successfully', [
                    'job_id' => $this->job->id,
                ]);
            } catch (\Throwable $eventError) {
                // Log the error but don't fail the cancellation
                \Log::error('CancelJob: Error firing event (job still canceled)', [
                    'job_id' => $this->job->id,
                    'error' => $eventError->getMessage(),
                    'file' => $eventError->getFile(),
                    'line' => $eventError->getLine(),
                ]);
                // Continue - the job is already canceled
            }

            session()->flash('success', __('Job canceled successfully.'));
            $this->closeModal();
            
            // Dispatch event to refresh the parent component
            $this->dispatch('job-canceled', jobId: $this->job->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions so Livewire can handle them
            throw $e;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            // Re-throw authorization exceptions
            throw $e;
        } catch (\Throwable $e) {
            // Log any other errors
            \Log::error('CancelJob: Error canceling job', [
                'job_id' => $this->job->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            session()->flash('error', __('An error occurred while canceling the job: ') . $e->getMessage());
            
            // Re-throw so Livewire can handle it
            throw $e;
        }
    }

    public function getCancellationReasons()
    {
        return config('pricing.cancellation.default_reasons', []);
    }

    public function getCancellationTypeOptions()
    {
        return [
            'CANCEL' => __('Auto-determine (based on timing)'),
            'show_no_go' => __('Show But No-Go ($225.00)'),
            'cancellation_24hr' => __('Cancellation Within 24hrs ($150.00)'),
            'cancel_without_billing' => __('Cancel Without Billing (No charge)'),
        ];
    }

    public function render()
    {
        return view('livewire.cancel-job');
    }
}
