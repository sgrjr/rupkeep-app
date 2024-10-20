<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Form;
use Livewire\Attributes\Validate;
use App\Models\Customer;
use App\Models\PilotCarJob as Job;

class ShowPilotCarJob extends Component
{
    
    public $customers = [];

    public $rates = [];

    public function mount(){

    }

    public function render()
    {
        return view('livewire.show-pilot-car-job');
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
