<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Casco Bay Pilot Car Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the pricing rates and rules for invoicing.
    | Rates can be selected per job via the rate_code field.
    |
    */

    'rates' => [
        // Per mile rates
        'lead_chase_per_mile' => [
            'name' => 'Lead / Chase',
            'description' => '$2.00 per mile',
            'rate_per_mile' => 2.00,
            'type' => 'per_mile',
        ],
        'lead_chase_over_18_wide' => [
            'name' => 'Lead / Chase (Over 18\' Wide)',
            'description' => '$2.10 per mile',
            'rate_per_mile' => 2.10,
            'type' => 'per_mile',
        ],
        
        // Flat rates
        'mini_flat_rate' => [
            'name' => 'Mini-Run',
            'description' => '$350.00 (125 miles or less)',
            'flat_amount' => 350.00,
            'max_miles' => 125,
            'type' => 'flat',
        ],
        'show_no_go' => [
            'name' => 'Show But No-Go',
            'description' => '$225.00 (Lead or Chase)',
            'flat_amount' => 225.00,
            'type' => 'flat',
        ],
        'cancellation_24hr' => [
            'name' => 'Cancellation (Within 24hrs)',
            'description' => '$150.00',
            'flat_amount' => 150.00,
            'type' => 'flat',
        ],
        'cancel_without_billing' => [
            'name' => 'Cancel Without Billing',
            'description' => 'No charge',
            'flat_amount' => 0.00,
            'type' => 'flat',
        ],
        'day_downtime' => [
            'name' => 'Day Downtime',
            'description' => 'Applicable to breakdowns or over stays that hold the pilot car in service but not working',
            'flat_amount' => 450.00,
            'type' => 'flat',
        ],
        'day_rate' => [
            'name' => 'Day Rate',
            'description' => 'A per day minimum (not to exceed 8hrs). Extra charge may apply to cover time/mileage and hotel on loads that exceed 8 hrs. to complete and return home.',
            'flat_amount' => 575.00,
            'max_hours' => 8,
            'type' => 'flat',
        ],
    ],

    'charges' => [
        'wait_time' => [
            'name' => 'Wait Time',
            'rate_per_hour' => 25.00,
            'minimum_hours' => 1, // Charged after first hour
        ],
        'extra_stop' => [
            'name' => 'Extra Stop',
            'rate_per_stop' => 30.00,
        ],
        'tolls' => [
            'name' => 'Tolls',
            'description' => 'Full reimbursement of tolls while escorting the oversized load',
            'type' => 'reimbursement', // Full amount, no markup
        ],
        'dead_head' => [
            'name' => 'Dead Head Miles',
            'description' => '$1.00 per mile, first 100 miles free (to pickup location only)',
            'rate_per_mile' => 1.00,
            'free_miles' => 100,
        ],
    ],

    'cancellation' => [
        'auto_determine' => true,
        'hours_before_pickup_for_24hr_charge' => 24,
        'default_reasons' => [
            'customer_requested' => 'Customer Requested',
            'weather_conditions' => 'Weather Conditions',
            'vehicle_breakdown' => 'Vehicle Breakdown',
            'driver_unavailable' => 'Driver Unavailable',
            'load_not_ready' => 'Load Not Ready',
            'permit_issues' => 'Permit Issues',
            'other' => 'Other',
        ],
    ],

    'payment_terms' => [
        'due_immediately' => true,
        'grace_period_days' => 30,
        'late_fee_percentage' => 10.0, // 10% per 30-day period
        'late_fee_period_days' => 30,
        'terms_text' => 'Payment is due upon submission of invoices. Invoices will be considered past due after the first 30 days from the date of the invoice. 10% interest will be charged every 30 days, on past due invoices.',
    ],

    // Legacy rate codes for backward compatibility
    'legacy_rates' => [
        'per_mile_rate_1_00' => ['rate_per_mile' => 1.00],
        'per_mile_rate_1_25' => ['rate_per_mile' => 1.25],
        'per_mile_rate_1_50' => ['rate_per_mile' => 1.50],
        'per_mile_rate_2_00' => ['rate_per_mile' => 2.00],
        'per_mile_rate_2_25' => ['rate_per_mile' => 2.25],
        'per_mile_rate_2_50' => ['rate_per_mile' => 2.50],
        'per_mile_rate_2_75' => ['rate_per_mile' => 2.75],
        'per_mile_rate_3_00' => ['rate_per_mile' => 3.00],
        'per_mile_rate_3_25' => ['rate_per_mile' => 3.25],
        'per_mile_rate_3_50' => ['rate_per_mile' => 3.50],
        'flat_rate' => ['type' => 'flat'],
        'flat_rate_excludes_expenses' => ['type' => 'flat', 'excludes_expenses' => true],
    ],
];

