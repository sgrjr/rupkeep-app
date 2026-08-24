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
 
    // Either an existing customer id, or the NEW_CUSTOMER sentinel meaning
    // "create the one named below". Deliberately not `required` here: the
    // mode is checked in createJob(), because a rule demanding an id
    // contradicted the new-customer branch that has always existed further
    // down this class and silently disabled it (TASK-395).
    #[Validate('nullable')]
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

    /** Sentinel value for the "create a new customer" option in the picker. */
    public const NEW_CUSTOMER = '__new__';

    public $customers = [];

    public $rates = [];

    public $drivers = [];

    public $truckDrivers = [];

    public function mount(){
       $user = Auth::user();
       $customers = $user->organization->customers;

       $this->customers = [
        ['name'=>'(none selected)', 'value'=> null],
        ['name'=>'+ Create a new customer', 'value'=> self::NEW_CUSTOMER],
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
           $this->form->rate_code = PilotCarJob::DEFAULT_RATE_CODE;
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

        // Validate on submit. Without this the form relied entirely on
        // real-time #[Validate] feedback, which only fires on properties the
        // user actually touched -- so a job could be created with required
        // fields never filled in, as long as those inputs were never focused
        // (TASK-395). EditPilotCarJob::save() has always done this.
        $this->form->validate();

        if (! $this->resolveCustomerMode()) {
            return;
        }

        $organization = Auth::user()->organization;

        $form = $this->form->all();

        // Ensure rate_code is set (default if empty)
        if (empty($form['rate_code']) || empty($this->form->rate_code)) {
            $form['rate_code'] = $this->form->rate_code ?? PilotCarJob::DEFAULT_RATE_CODE;
        } else {
            $form['rate_code'] = $this->form->rate_code;
        }

        if($this->form->customer_id === self::NEW_CUSTOMER){
            $this->form->customer_id = null;
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
        $this->form->rate_code = PilotCarJob::DEFAULT_RATE_CODE;
        $this->form->rate_value = PilotCarJob::defaultRateValue($this->form->rate_code, $user->organization_id);
        $this->dispatch('saved');
        return redirect()->route('my.jobs.show', ['job'=>$job->id]);
    }

    /**
     * One control decides the mode, the other follows (TASK-395).
     *
     * Two always-live inputs meaning the same thing were impossible to validate
     * and confusing to use: the form demanded a customer id while offering a
     * field whose entire purpose was to be used instead of one.
     *
     * Returns false when the choice is incoherent, having already surfaced the
     * error on the field the user needs to fix.
     */
    private function resolveCustomerMode(): bool
    {
        if ($this->form->customer_id === self::NEW_CUSTOMER) {
            if (blank($this->form->new_customer_name)) {
                $this->addError('form.new_customer_name', __('Enter a name for the new customer.'));

                return false;
            }

            return true;
        }

        if (blank($this->form->customer_id)) {
            $this->addError('form.customer_id', __('Choose a customer, or pick "+ Create a new customer" to add one.'));

            return false;
        }

        // A picked customer must be a real one belonging to this organization.
        $belongs = Auth::user()->organization
            ->customers()
            ->whereKey($this->form->customer_id)
            ->exists();

        if (! $belongs) {
            $this->addError('form.customer_id', __('That customer could not be found.'));

            return false;
        }

        return true;
    }

    protected function sanitizeRateValue($rawValue, ?string $rateCode): ?string    {
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
