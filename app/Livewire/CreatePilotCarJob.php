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

    #[Validate('nullable|numeric|min:0')]
    public $mini_addon_amount = null;

    #[Validate('nullable|string|min:3')]
    public $memo = null;

    #[Validate('nullable|string|max:1000')]
    public $public_memo = null;

    #[Validate('nullable|exists:users,id')]
    public $default_driver_id = null;

    #[Validate('nullable|exists:customer_contacts,id')]
    public $default_truck_driver_id = null;

    // Inline "add a truck driver" (TASK-362). Transient: resolved to a
    // CustomerContact in createJob() and stripped before the job is written.
    #[Validate('nullable|string|max:255')]
    public $new_truck_driver_name = null;

    #[Validate('nullable|string|max:255')]
    public $new_truck_driver_phone = null;

}

class CreatePilotCarJob extends Component
{
    use \App\Livewire\Concerns\ResolvesTruckDriverContact;

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
       
       // Truck drivers follow the selected customer. On a fresh form there is
       // none yet; on a re-render (validation error, wire:model round-trip) the
       // customer may already be set, and rebuilding the placeholder-only list
       // would blank out a selection the user already made.
       if ($this->form->customer_id) {
           $this->loadTruckDriversForCustomer($this->form->customer_id);
       } else {
           $this->truckDrivers = [
               ['name' => '(select customer first)', 'value' => null]
           ];
       }

       if (empty($this->form->rate_code)) {
           $this->form->rate_code = 'per_mile_rate_2_00';
       }

       $this->form->rate_value = $this->form->rate_value ?? PilotCarJob::defaultRateValue($this->form->rate_code, $user->organization_id);
    }

    public function updatedFormCustomerId($value)
    {
        $this->loadTruckDriversForCustomer($value);
    }

    protected function loadTruckDriversForCustomer($customerId): void
    {
        $this->truckDrivers = [
            ['name' => '(none selected)', 'value' => null]
        ];

        if (! $customerId) {
            return;
        }

        CustomerContact::where('customer_id', $customerId)
            ->get()
            ->each(function ($contact) {
                $label = $contact->phone ? $contact->name . ' (' . $contact->phone . ')' : $contact->name;
                $this->truckDrivers[] = ['name' => $label, 'value' => $contact->id];
            });
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

        // Resolve the inline truck driver AFTER the customer is settled above, so
        // a driver typed alongside a brand-new company lands on that company
        // rather than being dropped (TASK-362).
        $resolvedTruckDriverId = $this->resolveTruckDriverContact(
            $this->form->new_truck_driver_name,
            $this->form->new_truck_driver_phone,
            $form['customer_id'] ?? null,
            $organization->id,
        );

        if ($resolvedTruckDriverId) {
            $form['default_truck_driver_id'] = $resolvedTruckDriverId;
        }

        // Transient form-only fields — not columns on pilot_car_jobs.
        unset($form['new_truck_driver_name'], $form['new_truck_driver_phone']);

        // Sanitize and set rate_value explicitly
        $form['rate_value'] = $this->sanitizeRateValue($this->form->rate_value, $form['rate_code']);

        // Sanitize the additive mini add-on amount (nullable; blank leaves it unset)
        $form['mini_addon_amount'] = $this->sanitizeMiniAddonAmount($this->form->mini_addon_amount);

        // Double-count guard (TASK-335): the additive mini add-on cannot stack on
        // a job already billed at the mini flat rate — that would charge the mini
        // twice. Reject the combination; the additive design stays intact for every
        // other rate code.
        if ($form['rate_code'] === 'mini_flat_rate' && $form['mini_addon_amount'] !== null && (float) $form['mini_addon_amount'] > 0) {
            $this->addError('form.mini_addon_amount', __('A Mini Add-On cannot be applied to a Mini-Run (mini_flat_rate) job — that would charge the mini rate twice. Remove the add-on or choose a different rate.'));

            return;
        }

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

    protected function sanitizeMiniAddonAmount($rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9\.\-]/', '', (string) $rawValue);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return number_format(max(0, (float) $normalized), 2, '.', '');
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
