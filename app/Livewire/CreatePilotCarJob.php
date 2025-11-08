<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Customer;
use App\Models\PilotCarJob;

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

}

class CreatePilotCarJob extends Component
{

    public NewJobForm $form;

    public $customers = [];

    public $rates = [];

    public function mount(){
       $customers = auth()->user()->organization->customers;

       $this->customers = [
        ['name'=>'(none selected)', 'value'=> null]
       ];

       foreach($customers as $customer){
        $this->customers[] = [
            'name'=> $customer->name, 'value' => $customer->id
        ];
       }
       
       $this->rates = PilotCarJob::rates();

       if (empty($this->form->rate_code)) {
           $this->form->rate_code = 'per_mile_rate_2_00';
       }

       $this->form->rate_value = $this->form->rate_value ?? PilotCarJob::defaultRateValue($this->form->rate_code);
    }

    public function render()
    {
        return view('livewire.create-pilot-car-job');
    }

    public function createJob(){

        $organization = auth()->user()->organization;

        $form = $this->form->all();

        if (empty($form['rate_code'])) {
            $form['rate_code'] = 'per_mile_rate_2_00';
        }

        if (empty($form['rate_code'])) {
            $form['rate_code'] = 'per_mile_rate_2_00';
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

        $form['rate_value'] = $this->sanitizeRateValue($this->form->rate_value, $form['rate_code']);

        $job = $organization->jobs()->create($form);
        $this->form->reset();
        $this->form->rate_code = 'per_mile_rate_2_00';
        $this->form->rate_value = PilotCarJob::defaultRateValue($this->form->rate_code);
        $this->dispatch('saved');
        return redirect()->route('my.jobs.show', ['job'=>$job->id]);
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
        } else {
            $this->form->rate_value = $this->form->rate_value ?? null;
        }
    }
}
