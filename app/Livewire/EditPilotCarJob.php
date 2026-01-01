<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Customer;
use App\Models\PilotCarJob;
use App\Models\User;
use App\Models\CustomerContact;
use Illuminate\Support\Facades\Auth;

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

    #[Validate('nullable|string|max:1000')]
    public $public_memo = null;

    #[Validate('nullable|exists:users,id')]
    public $default_driver_id = null;

    #[Validate('nullable|exists:customer_contacts,id')]
    public $default_truck_driver_id = null;

}

class EditPilotCarJob extends Component
{
    
    public EditJobForm $form;
    
    public $customers = [];

    public $rates = [];

    public $drivers = [];

    public $truckDrivers = [];

    public $job;

    public function mount(Int $job){
       $user = Auth::user();
       $this->customers = $user->organization->customers;
       $this->rates = PilotCarJob::rates($user->organization_id);
       
       // Load drivers
       $this->drivers = [
           ['name' => '(none selected)', 'value' => null]
       ];
       User::where('organization_id', $user->organization_id)
           ->get()
           ->each(fn($user) => $this->drivers[] = ['name' => $user->name, 'value' => $user->id]);
       
       $jobModel = PilotCarJob::find($job);

        if($this->authorize('update', $jobModel)){
            $this->job = $jobModel;
            $this->form->job_no = $jobModel->job_no;
            $this->form->customer_id = $jobModel->customer_id;
            $this->form->new_customer_name = null;
            $this->form->scheduled_pickup_at = $jobModel->scheduled_pickup_at;
            $this->form->scheduled_delivery_at = $jobModel->scheduled_delivery_at;
            $this->form->load_no = $jobModel->load_no;
            $this->form->pickup_address = $jobModel->pickup_address;
            $this->form->delivery_address = $jobModel->delivery_address;
            $this->form->check_no = $jobModel->check_no;
            $this->form->invoice_paid = $jobModel->invoice_paid;
            $this->form->invoice_no = $jobModel->invoice_no;
            $this->form->rate_code = $jobModel->rate_code;
            $this->form->rate_value = $jobModel->rate_value ?? PilotCarJob::defaultRateValue($jobModel->rate_code, $user->organization_id);
            $this->form->memo = $jobModel->memo;
            $this->form->public_memo = $jobModel->public_memo;
            $this->form->default_driver_id = $jobModel->default_driver_id;
            $this->form->default_truck_driver_id = $jobModel->default_truck_driver_id;
            
            // Load truck drivers for this customer
            if ($jobModel->customer_id) {
                $this->truckDrivers = [
                    ['name' => '(none selected)', 'value' => null]
                ];
                CustomerContact::where('customer_id', $jobModel->customer_id)
                    ->get()
                    ->each(fn($contact) => $this->truckDrivers[] = ['name' => $contact->name, 'value' => $contact->id]);
            }
        }
    }

    public function render()
    {
        return view('livewire.edit-pilot-car-job');
    }

    public function saveJob(){
        $this->form->validate();

        $form = $this->form->all();
        
        // Ensure rate_code is explicitly set
        $form['rate_code'] = $this->form->rate_code ?? $this->job->rate_code ?? 'per_mile_rate_2_00';
        
        // Sanitize and set rate_value explicitly
        $form['rate_value'] = $this->sanitizeRateValue($this->form->rate_value, $form['rate_code']);

        $this->job->update($form);
        $this->dispatch('saved');
        return redirect()->route('jobs.show', ['job'=>$this->job->id]);
    }

    protected function sanitizeRateValue($rawValue, ?string $rateCode): ?string
    {
        $value = null;
        $user = Auth::user();

        if ($rawValue !== null && $rawValue !== '') {
            $normalized = preg_replace('/[^0-9\.\-]/', '', (string) $rawValue);

            if ($normalized !== '' && is_numeric($normalized)) {
                $value = number_format((float) $normalized, 2, '.', '');
            }
        }

        if ($value === null) {
            $value = PilotCarJob::defaultRateValue($rateCode, $user->organization_id);
        }

        return $value;
    }

    public function updatedFormRateCode($value): void
    {
        $user = Auth::user();
        $default = PilotCarJob::defaultRateValue($value, $user->organization_id);

        if ($default !== null) {
            $this->form->rate_value = $default;
        }
    }
}
