<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Customer;
use App\Models\PilotCarJob;

class EditJobForm extends Form
{
    #[Validate('required|string|max:255')]
    public $job_no = null;
 
    #[Validate('required|exists:customers,id|min:1')]
    public $customer_id = null;

    #[Validate('nullable|string|min:3')]
    public $new_customer_name = null;

    #[Validate('nullable|string|min:8')]
    public $scheduled_pickup_at = null;

    #[Validate('nullable|string|min:8')]
    public $scheduled_delivery_at = null;
    #[Validate('required|string|max:255')]
    public $load_no = null;

    #[Validate('required|string|max:255')]
    public $pickup_address = null;

    #[Validate('required|string|max:255')]
    public $delivery_address = null;

    #[Validate('nullable|string|max:255')]
    public $check_no = null;

    #[Validate('nullable|string|max:255')]
    public $invoice_paid = null;

    #[Validate('nullable|string|max:255')]
    public $invoice_no = null;

    #[Validate('required|string|max:255')]
    public $rate_code = null;

    #[Validate('nullable|numeric')]
    public $rate_value = null;

    #[Validate('nullable|string|min:3')]
    public $memo = null;

}

class EditPilotCarJob extends Component
{
    
    public EditJobForm $form;
    
    public $customers = [];

    public $rates = [];

    public $job;

    public function mount(Int $job){
       $this->customers = auth()->user()->organization->customers;
       $this->rates = PilotCarJob::rates();
       $job = PilotCarJob::find($job);

        if($this->authorize('update', $job)){
            $this->job = $job;
            $this->form->job_no = $job->job_no;
            $this->form->customer_id = $job->customer_id;
            $this->form->new_customer_name = null;
            $this->form->scheduled_pickup_at = $job->scheduled_pickup_at;
            $this->form->scheduled_delivery_at = $job->scheduled_delivery_at;
            $this->form->load_no = $job->load_no;
            $this->form->pickup_address = $job->pickup_address;
            $this->form->delivery_address = $job->delivery_address;
            $this->form->check_no = $job->check_no;
            $this->form->invoice_paid = $job->invoice_paid;
            $this->form->invoice_no = $job->invoice_no;
            $this->form->rate_code = $job->rate_code;
            $this->form->rate_value = $job->rate_value ?? PilotCarJob::defaultRateValue($job->rate_code);
            $this->form->memo = $job->memo;
        }
    }

    public function render()
    {
        return view('livewire.edit-pilot-car-job');
    }

    public function saveJob(){
        $this->form->validate();

        $form = $this->form->all();
        $form['rate_value'] = $this->sanitizeRateValue($this->form->rate_value, $this->form->rate_code);

        $this->job->update($form);
        $this->dispatch('saved');
        return redirect()->route('jobs.show', ['job'=>$this->job->id]);
    }

    protected function sanitizeRateValue($rawValue, ?string $rateCode): ?string
    {
        $value = null;

        if ($rawValue !== null && $rawValue !== '') {
            $normalized = preg_replace('/[^0-9\.\-]/', '', (string) $rawValue);

            if ($normalized !== '' && is_numeric($normalized)) {
                $value = number_format((float) $normalized, 2, '.', '');
            }
        }

        if ($value === null) {
            $value = PilotCarJob::defaultRateValue($rateCode);
        }

        return $value;
    }

    public function updatedFormRateCode($value): void
    {
        $default = PilotCarJob::defaultRateValue($value);

        if ($default !== null) {
            $this->form->rate_value = $default;
        }
    }
}
