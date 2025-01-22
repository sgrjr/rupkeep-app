<?php

return [
    'super_user' => [
        'name' => env('SUPER_NAME'),
        'email' => env('SUPER_EMAIL'),
        'password' => env('SUPER_PASSWORD'),
        'organization_role' => 'administrator'
    ],
    'cbpc_users' =>[
        [
            'name' => 'Mary Reynolds',
            'email' => env('u1_email'),
            'password' => env('u1_password'),
            'organization_role' => 'administrator',
            'notification_address' => null
        ],
        [
            'name' => 'Matthew Reynolds',
            'email' => env('u2_email'),
            'password' => env('u2_password'),
            'organization_role' => 'administrator',
            'notification_address' => '2074168659@mms.uscc.net'
        ],
        [
            'name' => 'Christina Reynolds',
            'email' => 'princessrina31@icloud.com',
            'password' => env('default_password'),
            'organization_role' => 'driver',
            'notification_address' => null
        ],
        [
            'name' => 'Gail Field',
            'email' => 'gail@cascobaypc.com',
            'password' => env('default_password'),
            'organization_role' => 'driver',
            'notification_address' => null
        ],
        [
            'name' => 'Donald Cummings',
            'email' => 'donald@cascobaypc.com',
            'password' => env('default_password'),
            'organization_role' => 'driver',
            'notification_address' => ''
        ],
        [
            'name' => 'Dave Field',
            'email' => 'dave@cascobaypc.com',
            'password' => env('default_password'),
            'organization_role' => 'driver',
            'notification_address' => null
        ],
        [
            'name' => 'Temp Driver',
            'email' => 'tempdriver@cascobaypc.com',
            'password' => env('default_password'),
            'organization_role' => 'driver',
            'notification_address' => null
        ]
    ],

    'cbpc_vehicles' => [
        [
              'name'=>'Car 001',
              'odometer' => '291305'
        ],
        [
              'name'=>'Car 002',
              'odometer' => '291305'
        ],
        [
              'name'=>'Car 003',
              'odometer' => '242504'
        ],
        [
              'name'=>'Car 004',
              'odometer' => '249492'
        ],
        [
              'name'=>'Car 005',
              'odometer' => null
        ]
    ],
    'cbpc' => [
        'name'=>'Casco Bay Pilot Car',
        'primary_contact'=>'cascopbaypc@gmail.com',
        'telephone'=>'207-712-8064',
        'fax'=>'',
        'email'=>'cascobaypc@gmail.com',
        'street'=>'303 Bridgton Road',
        'city'=>'East Baldwin',
        'state'=>'ME',
        'zip'=>'04024',
        'user_id'=>'',
        'logo_url'=>'',
        'website_url'=>'',
        'facebook_url' => 'https://www.facebook.com/cascobaypc'
    ],
    'organization' => [
        'name'=>'Reynolds Upkeep',
        'primary_contact'=>'Stephen Reynolds, Jr.',
        'telephone'=>'207-776-1085',
        'email'=>'stephengreynoldsjr@gmail.com',
        'street'=>'928 Old Post Road',
        'city'=>'Arundel',
        'state'=>'Maine',
        'zip'=>'04046'
    ]
];
