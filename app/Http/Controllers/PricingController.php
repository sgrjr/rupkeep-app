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
        // Shared with the roadmap and the legal pages (TASK-475).
        return \App\Support\PublicOrganization::resolve();
    }
}
