<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\PricingResolver;

class PricingController extends Controller
{
    /**
     * Display the public pricing page
     */
    public function show()
    {
        // Determine which organization to use
        $organization = $this->getDefaultOrganization();

        // Load pricing data. This is the same resolver /my/pricing edits
        // through, so an org's rename, rate change, or custom charge shows up
        // here without a deploy (TASK-377).
        $pricingData = PricingResolver::all($organization?->id);
        $pricingData['organization'] = $organization;

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
}
