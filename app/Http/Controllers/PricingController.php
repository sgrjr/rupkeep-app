<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\PricingSetting;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /**
     * Display the public pricing page
     */
    public function show()
    {
        // Determine which organization to use
        $organization = $this->getDefaultOrganization();
        
        // Load pricing data
        $pricingData = $this->loadPricingData($organization);
        
        return view('pricing', $pricingData);
    }

    /**
     * Get the default organization for pricing display
     * 
     * @return Organization|null
     */
    private function getDefaultOrganization(): ?Organization
    {
        // First, check if default_organization_id is set in config
        $defaultOrgId = config('pricing.default_organization_id');
        
        if ($defaultOrgId) {
            $organization = Organization::find($defaultOrgId);
            if ($organization) {
                return $organization;
            }
        }
        
        // If not set or not found, search for "Casco Bay Pilot Car"
        $organization = Organization::where('name', 'Casco Bay Pilot Car')->first();
        
        return $organization;
    }

    /**
     * Load pricing data from organization or config defaults
     * 
     * @param Organization|null $organization
     * @return array
     */
    private function loadPricingData(?Organization $organization): array
    {
        $organizationId = $organization?->id;
        
        // Load rates
        $configRates = config('pricing.rates', []);
        $rates = [];
        
        foreach ($configRates as $code => $config) {
            $rates[$code] = [
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'type' => $config['type'],
                'rate_per_mile' => $organizationId 
                    ? PricingSetting::getValueForOrganization($organizationId, "rates.{$code}.rate_per_mile", $config['rate_per_mile'] ?? null)
                    : ($config['rate_per_mile'] ?? null),
                'flat_amount' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "rates.{$code}.flat_amount", $config['flat_amount'] ?? null)
                    : ($config['flat_amount'] ?? null),
                'max_miles' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "rates.{$code}.max_miles", $config['max_miles'] ?? null)
                    : ($config['max_miles'] ?? null),
                'max_hours' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "rates.{$code}.max_hours", $config['max_hours'] ?? null)
                    : ($config['max_hours'] ?? null),
            ];
        }

        // Load charges
        $configCharges = config('pricing.charges', []);
        $charges = [];
        
        foreach ($configCharges as $key => $config) {
            $charges[$key] = [
                'name' => $config['name'],
                'description' => $config['description'] ?? null,
                'rate_per_hour' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "charges.{$key}.rate_per_hour", $config['rate_per_hour'] ?? null)
                    : ($config['rate_per_hour'] ?? null),
                'rate_per_stop' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "charges.{$key}.rate_per_stop", $config['rate_per_stop'] ?? null)
                    : ($config['rate_per_stop'] ?? null),
                'rate_per_mile' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "charges.{$key}.rate_per_mile", $config['rate_per_mile'] ?? null)
                    : ($config['rate_per_mile'] ?? null),
                'minimum_hours' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "charges.{$key}.minimum_hours", $config['minimum_hours'] ?? null)
                    : ($config['minimum_hours'] ?? null),
                'free_miles' => $organizationId
                    ? PricingSetting::getValueForOrganization($organizationId, "charges.{$key}.free_miles", $config['free_miles'] ?? null)
                    : ($config['free_miles'] ?? null),
                'type' => $config['type'] ?? null,
            ];
        }

        // Load cancellation settings
        $configCancellation = config('pricing.cancellation', []);
        $cancellation = [
            'auto_determine' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'cancellation.auto_determine', $configCancellation['auto_determine'] ?? true)
                : ($configCancellation['auto_determine'] ?? true),
            'hours_before_pickup_for_24hr_charge' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'cancellation.hours_before_pickup_for_24hr_charge', $configCancellation['hours_before_pickup_for_24hr_charge'] ?? 24)
                : ($configCancellation['hours_before_pickup_for_24hr_charge'] ?? 24),
        ];

        // Load payment terms
        $configPaymentTerms = config('pricing.payment_terms', []);
        $paymentTerms = [
            'due_immediately' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.due_immediately', $configPaymentTerms['due_immediately'] ?? true)
                : ($configPaymentTerms['due_immediately'] ?? true),
            'grace_period_days' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.grace_period_days', $configPaymentTerms['grace_period_days'] ?? 30)
                : ($configPaymentTerms['grace_period_days'] ?? 30),
            'late_fee_percentage' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.late_fee_percentage', $configPaymentTerms['late_fee_percentage'] ?? 10.0)
                : ($configPaymentTerms['late_fee_percentage'] ?? 10.0),
            'late_fee_period_days' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.late_fee_period_days', $configPaymentTerms['late_fee_period_days'] ?? 30)
                : ($configPaymentTerms['late_fee_period_days'] ?? 30),
            'terms_text' => $organizationId
                ? PricingSetting::getValueForOrganization($organizationId, 'payment_terms.terms_text', $configPaymentTerms['terms_text'] ?? '')
                : ($configPaymentTerms['terms_text'] ?? ''),
        ];

        return [
            'rates' => $rates,
            'charges' => $charges,
            'cancellation' => $cancellation,
            'payment_terms' => $paymentTerms,
            'organization' => $organization,
        ];
    }
}
