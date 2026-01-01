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

class NewJobForm extends Form
{
    #[Validate('required|string|max:255')]
    public $job_no = null;
 
    #[Validate('required|numeric|exists:customers,id|min:1')]
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

class CreatePilotCarJob extends Component
{

    public NewJobForm $form;

    public $customers = [];

    public $rates = [];

    public $drivers = [];

    public $truckDrivers = [];

    public function mount(){
       $user = Auth::user();
       $customers = $user->organization->customers;

       $this->customers = [
        ['name'=>'(none selected)', 'value'=> null]
       ];

       foreach($customers as $customer){
        $this->customers[] = [
            'name'=> $customer->name, 'value' => $customer->id
        ];
       }
       
       $this->rates = PilotCarJob::rates($user->organization_id);

       // Load drivers
       $this->drivers = [
           ['name' => '(none selected)', 'value' => null]
       ];
       User::where('organization_id', $user->organization_id)
           ->get()
           ->each(fn($user) => $this->drivers[] = ['name' => $user->name, 'value' => $user->id]);
       
       // Truck drivers will be loaded when customer is selected
       $this->truckDrivers = [
           ['name' => '(select customer first)', 'value' => null]
       ];

       if (empty($this->form->rate_code)) {
           $this->form->rate_code = 'per_mile_rate_2_00';
       }

       $this->form->rate_value = $this->form->rate_value ?? PilotCarJob::defaultRateValue($this->form->rate_code, $user->organization_id);
    }

    public function updatedFormCustomerId($value)
    {
        // Reload truck drivers when customer changes
        $this->truckDrivers = [
            ['name' => '(none selected)', 'value' => null]
        ];
        
        if ($value) {
            CustomerContact::where('customer_id', $value)
                ->get()
                ->each(fn($contact) => $this->truckDrivers[] = ['name' => $contact->name, 'value' => $contact->id]);
        }
    }

    public function render()
    {
        return view('livewire.create-pilot-car-job');
    }

    public function createJob(){

        $organization = Auth::user()->organization;

        $form = $this->form->all();

        // Ensure rate_code is set (default if empty)
        if (empty($form['rate_code']) || empty($this->form->rate_code)) {
            $form['rate_code'] = $this->form->rate_code ?? 'per_mile_rate_2_00';
        } else {
            $form['rate_code'] = $this->form->rate_code;
        }

        if(empty($this->form->customer_id ) && !empty($this->form->new_customer_name)){

            $existing_customers = Customer::where('organization_id', $organization->id)->get();

            if($existing_customers->count() > 0){
                $customer_id = false;
                $name_id = trim(str_replace(' ', '', strtolower($this->form->new_customer_name)));
                $matched = false;
                foreach($existing_customers as $c){
                    $c_name_id = trim(str_replace(' ', '', strtolower($c->name)));
                    if(!$matched && $name_id === $c_name_id){
                        $matched = true;
                        $customer_id = $c->id;
                    }
                }
            }else{
                $customer_id = false;
            }

            if(!$customer_id){
                $customer = $organization->customers()->create([
                    'name' => $this->form->new_customer_name
                ]);
                $customer_id = $customer->id;
            }

            $form['customer_id'] = $customer_id;
        }

        // Sanitize and set rate_value explicitly
        $form['rate_value'] = $this->sanitizeRateValue($this->form->rate_value, $form['rate_code']);

        $user = Auth::user();
        $job = $user->organization->jobs()->create($form);
        $this->form->reset();
        $this->form->rate_code = 'per_mile_rate_2_00';
        $this->form->rate_value = PilotCarJob::defaultRateValue($this->form->rate_code, $user->organization_id);
        $this->dispatch('saved');
        return redirect()->route('my.jobs.show', ['job'=>$job->id]);
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
        } else {
            $this->form->rate_value = $this->form->rate_value ?? null;
        }
    }
}
