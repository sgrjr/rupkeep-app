<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PricingSetting;
use App\Models\Organization;
use App\Services\PricingResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ManagePricing extends Component
{
    use AuthorizesRequests;

    public $organization;
    public $rates = [];
    public $charges = [];
    public $cancellation = [];
    public $paymentTerms = [];
    public $activeTab = 'rates';

    /** The "add a charge" form on the Charges tab (TASK-377). */
    public $newCharge = [
        'name' => '',
        'description' => '',
        'unit' => 'none',
        'amount' => '',
    ];

    public function mount()
    {
        $user = Auth::user();

        // Super users can manage any organization's pricing
        // Regular admins can only manage their own organization
        if ($user->isSuper() && request()->has('organization_id')) {
            $this->organization = Organization::findOrFail(request('organization_id'));
        } else {
            $this->organization = $user->organization;
        }

        $this->authorize('createJob', $this->organization);

        $this->loadPricingData();
    }

    public function loadPricingData()
    {
        // One resolver, shared with the public /pricing page, so the two can
        // never disagree about what this org's price sheet says (TASK-377).
        $pricing = PricingResolver::all($this->organization->id);

        $this->rates = $pricing['rates'];
        $this->charges = $pricing['charges'];
        $this->cancellation = $pricing['cancellation'];
        $this->paymentTerms = $pricing['payment_terms'];
    }

    public function updateRate($code, $field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $key = "rates.{$code}.{$field}";
        $type = in_array($field, ['rate_per_mile', 'flat_amount', 'max_miles', 'max_hours']) ? 'float' : 'string';

        if ($value === '' || $value === null) {
            // Delete to revert to config default
            PricingSetting::deleteForOrganization($this->organization->id, $key);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $key,
                $value,
                $type,
                'rates'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Pricing updated successfully.'));
    }

    public function updateCharge($key, $field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $isCustom = PricingResolver::isCustomCharge($this->organization->id, $key);

        // A custom charge has no config entry to fall back to, so clearing its
        // name would publish an unnamed card on the public price sheet rather
        // than reverting anything.
        if ($isCustom && $field === 'name' && trim((string) $value) === '') {
            $this->loadPricingData();
            session()->flash('error', __('A charge you added needs a name. Remove it instead if you no longer publish it.'));

            return;
        }

        $settingKey = "charges.{$key}.{$field}";
        $type = in_array($field, PricingResolver::CHARGE_NUMERIC_FIELDS) ? 'float' : 'string';

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $settingKey);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $settingKey,
                $value,
                $type,
                'charges'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Charge updated successfully.'));
    }

    /**
     * Publish a new entry on this org's price sheet (TASK-377).
     */
    public function addCharge()
    {
        $this->authorize('createJob', $this->organization);

        $this->validate([
            'newCharge.name' => ['required', 'string', 'max:120'],
            'newCharge.description' => ['nullable', 'string', 'max:500'],
            'newCharge.unit' => ['required', Rule::in(array_keys(PricingResolver::CUSTOM_UNITS))],
            'newCharge.amount' => [
                Rule::requiredIf(fn () => ($this->newCharge['unit'] ?? 'none') !== 'none'),
                'nullable',
                'numeric',
                'min:0',
            ],
        ], [], [
            'newCharge.name' => __('name'),
            'newCharge.description' => __('description'),
            'newCharge.unit' => __('unit'),
            'newCharge.amount' => __('amount'),
        ]);

        PricingResolver::addCustomCharge(
            $this->organization->id,
            trim($this->newCharge['name']),
            $this->newCharge['description'] === null ? null : trim($this->newCharge['description']),
            $this->newCharge['unit'],
            $this->newCharge['amount']
        );

        $this->newCharge = ['name' => '', 'description' => '', 'unit' => 'none', 'amount' => ''];
        $this->activeTab = 'charges';

        $this->loadPricingData();
        session()->flash('success', __('Charge added to your price list.'));
    }

    /**
     * Take an org-added entry back off the price sheet. Config-backed charges
     * are not removable -- invoice math reads two of them by name.
     */
    public function removeCharge($key)
    {
        $this->authorize('createJob', $this->organization);

        $removed = PricingResolver::removeCustomCharge($this->organization->id, $key);

        $this->loadPricingData();

        if ($removed) {
            session()->flash('success', __('Charge removed from your price list.'));
        } else {
            session()->flash('error', __('That charge is part of the standard price list and cannot be removed.'));
        }
    }

    public function updateCancellation($field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $key = "cancellation.{$field}";
        $type = $field === 'auto_determine' ? 'boolean' : ($field === 'hours_before_pickup_for_24hr_charge' ? 'integer' : 'string');

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $key);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $key,
                $value,
                $type,
                'cancellation'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Cancellation settings updated successfully.'));
    }

    public function updatePaymentTerms($field, $value)
    {
        $this->authorize('createJob', $this->organization);

        $key = "payment_terms.{$field}";
        $type = match($field) {
            'due_immediately' => 'boolean',
            'grace_period_days', 'late_fee_period_days' => 'integer',
            'late_fee_percentage' => 'float',
            default => 'string',
        };

        if ($value === '' || $value === null) {
            PricingSetting::deleteForOrganization($this->organization->id, $key);
        } else {
            PricingSetting::setValueForOrganization(
                $this->organization->id,
                $key,
                $value,
                $type,
                'payment_terms'
            );
        }

        $this->loadPricingData();
        session()->flash('success', __('Payment terms updated successfully.'));
    }

    public function render()
    {
        return view('livewire.manage-pricing', [
            'unitOptions' => [
                'none' => __('No rate — information only'),
                'per_hour' => __('Per hour'),
                'per_stop' => __('Per stop'),
                'per_mile' => __('Per mile'),
                'flat' => __('Flat amount'),
            ],
        ]);
    }
}
