<?php

namespace App\Support;

use App\Models\Organization;

/**
 * The organization a public page speaks for when nobody is signed in: the
 * pricing page, the public roadmap, the terms and privacy pages (TASK-475).
 *
 * PRICING_DEFAULT_ORGANIZATION_ID wins; otherwise the organization named
 * "Casco Bay Pilot Car"; otherwise the first organization there is. One
 * resolver so the public pages cannot disagree about whose site this is.
 */
class PublicOrganization
{
    public static function resolve(): ?Organization
    {
        $defaultOrgId = config('pricing.default_organization_id');

        if ($defaultOrgId && ($organization = Organization::find($defaultOrgId))) {
            return $organization;
        }

        return Organization::where('name', 'Casco Bay Pilot Car')->first()
            ?? Organization::orderBy('id')->first();
    }
}
