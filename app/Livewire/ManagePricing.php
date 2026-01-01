<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\PricingSetting;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
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

    public function mount()
    {
        $user = Auth::user();
        
        // Super users can manage any organization's pricing
        // Regular admins can only manage their own organization
        if ($user->is_super && request()->has('organization_id')) {
            $this->organization = Organization::findOrFail(request('organization_id'));
        } else {
            $this->organization = $user->organization;
        }

        $this->authorize('createJob', $this->organization);
        
        $this->loadPricingData();
    }

    public function loadPricingData()
    {
        // Load rates from config as base structure
        $configRates = config('pricing.rates', []);
        $this->rates = [];
        
        foreach ($configRates as $code => $config) {
            $this->rates[$code] = [
                'name' => $config['name'],
                'description' => $config['description'],
                'type' => $config['type'],
                'rate_per_mile' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "rates.{$code}.rate_per_mile",
                    $config['rate_per_mile'] ?? null
                ),
                'flat_amount' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "rates.{$code}.flat_amount",
                    $config['flat_amount'] ?? null
                ),
                'max_miles' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "rates.{$code}.max_miles",
                    $config['max_miles'] ?? null
                ),
                'max_hours' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "rates.{$code}.max_hours",
                    $config['max_hours'] ?? null
                ),
            ];
        }

        // Load charges
        $configCharges = config('pricing.charges', []);
        $this->charges = [];
        
        foreach ($configCharges as $key => $config) {
            $this->charges[$key] = [
                'name' => $config['name'],
                'description' => $config['description'] ?? null,
                'rate_per_hour' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "charges.{$key}.rate_per_hour",
                    $config['rate_per_hour'] ?? null
                ),
                'rate_per_stop' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "charges.{$key}.rate_per_stop",
                    $config['rate_per_stop'] ?? null
                ),
                'rate_per_mile' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "charges.{$key}.rate_per_mile",
                    $config['rate_per_mile'] ?? null
                ),
                'minimum_hours' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "charges.{$key}.minimum_hours",
                    $config['minimum_hours'] ?? null
                ),
                'free_miles' => PricingSetting::getValueForOrganization(
                    $this->organization->id,
                    "charges.{$key}.free_miles",
                    $config['free_miles'] ?? null
                ),
            ];
        }

        // Load cancellation settings
        $configCancellation = config('pricing.cancellation', []);
        $this->cancellation = [
            'auto_determine' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'cancellation.auto_determine',
                $configCancellation['auto_determine'] ?? true
            ),
            'hours_before_pickup_for_24hr_charge' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'cancellation.hours_before_pickup_for_24hr_charge',
                $configCancellation['hours_before_pickup_for_24hr_charge'] ?? 24
            ),
        ];

        // Load payment terms
        $configPaymentTerms = config('pricing.payment_terms', []);
        $this->paymentTerms = [
            'due_immediately' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'payment_terms.due_immediately',
                $configPaymentTerms['due_immediately'] ?? true
            ),
            'grace_period_days' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'payment_terms.grace_period_days',
                $configPaymentTerms['grace_period_days'] ?? 30
            ),
            'late_fee_percentage' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'payment_terms.late_fee_percentage',
                $configPaymentTerms['late_fee_percentage'] ?? 10.0
            ),
            'late_fee_period_days' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'payment_terms.late_fee_period_days',
                $configPaymentTerms['late_fee_period_days'] ?? 30
            ),
            'terms_text' => PricingSetting::getValueForOrganization(
                $this->organization->id,
                'payment_terms.terms_text',
                $configPaymentTerms['terms_text'] ?? ''
            ),
        ];
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
        
        $settingKey = "charges.{$key}.{$field}";
        $type = in_array($field, ['rate_per_hour', 'rate_per_stop', 'rate_per_mile', 'minimum_hours', 'free_miles']) ? 'float' : 'string';
        
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
        return view('livewire.manage-pricing');
    }
}
