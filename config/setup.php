<?php

return [
    'super_user' => [
        'name' => env('SUPER_NAME'),
        'email' => env('SUPER_EMAIL'),
        'password' => env('SUPER_PASSWORD'),
        'organization_role' => 'administrator'
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
