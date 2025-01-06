<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Customer;

class NewJobForm extends Form
{
    #[Validate('required|string|max:255')]
    public $job_no = null;
 
    #[Validate('required|numeric|exists:customers,id|min:1')]
    public $customer_id = null;

    #[Validate('string|min:6')]
    public $new_customer_name = null;

    #[Validate('string|min:8')]
    public $scheduled_pickup_at = null;

    #[Validate('string|min:8')]
    public $scheduled_delivery_at = null;
    #[Validate('required|string|max:255')]
    public $load_no = null;

    #[Validate('required|string|max:255')]
    public $pickup_address = null;

    #[Validate('required|string|max:255')]
    public $delivery_address = null;

    #[Validate('string|max:255')]
    public $check_no = null;

    #[Validate('string|max:255')]
    public $invoice_paid = null;

    #[Validate('string|max:255')]
    public $invoice_no = null;

    #[Validate('string|max:255')]
    public $rate_code = null;

    #[Validate('string|max:255')]
    public $flat_rate_value = null;

    #[Validate('string|min:8')]
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
       
       $this->rates = [
            (Object)['value'=>'per_mile_rate_2_00','title'=>'$2.00 Per Mile (default)'],
            (Object)['value'=>'per_mile_rate_1_00','title'=>'$1.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_1_25','title'=>'$1.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_1_50','title'=>'$1.50 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_00','title'=>'$2.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_25','title'=>'$2.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_50','title'=>'$2.50 Per Mile'],
            (Object)['value'=>'per_mile_rate_2_75','title'=>'$2.75 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_00','title'=>'$3.00 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_25','title'=>'$3.25 Per Mile'],
            (Object)['value'=>'per_mile_rate_3_50','title'=>'$3.50 Per Mile'],
            (Object)['value'=>'new_per_mile_rate','title'=>'NEW Per Mile Rate (enter value below)'],
            (Object)['value'=>'flat_rate_excludes_expenses','title'=>'Flat Price (excludes expenses)'],
            (Object)['value'=>'flat_rate','title'=>'Flat Price (includes Expenses)'],
        ];
    }

    public function render()
    {
        return view('livewire.create-pilot-car-job');
    }

    public function createJob(){

        $organization = auth()->user()->organization;

        $form = $this->form->all();

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

        $job = $organization->jobs()->create($form);
        $this->form->reset();
        $this->dispatch('saved');
        return redirect()->route('my.jobs.show', ['job'=>$job->id]);
    }
}
