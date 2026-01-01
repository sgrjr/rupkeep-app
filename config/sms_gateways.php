<?php

/**
 * SMS Provider Email Gateways
 * 
 * Format: phone_number@gateway_domain
 * 
 * Common US carriers:
 * - AT&T: number@txt.att.net or number@mms.att.net
 * - Verizon: number@vtext.com or number@vzwpix.com
 * - T-Mobile: number@tmomail.net
 * - Sprint: number@messaging.sprintpcs.com or number@pm.sprint.com
 * - US Cellular: number@email.uscc.net or number@mms.uscc.net
 * - Cricket: number@sms.cricketwireless.net or number@mms.cricketwireless.net
 * - Boost Mobile: number@sms.myboostmobile.com
 * - Metro PCS: number@mymetropcs.com
 * - Virgin Mobile: number@vmobl.com
 * - Google Fi: number@msg.fi.google.com
 */

return [
    'providers' => [
        'att' => [
            'name' => 'AT&T',
            'sms' => '@txt.att.net',
            'mms' => '@mms.att.net',
        ],
        'verizon' => [
            'name' => 'Verizon',
            'sms' => '@vtext.com',
            'mms' => '@vzwpix.com',
        ],
        'tmobile' => [
            'name' => 'T-Mobile',
            'sms' => '@tmomail.net',
            'mms' => '@tmomail.net',
        ],
        'sprint' => [
            'name' => 'Sprint',
            'sms' => '@messaging.sprintpcs.com',
            'mms' => '@pm.sprint.com',
        ],
        'uscc' => [
            'name' => 'US Cellular',
            'sms' => '@email.uscc.net',
            'mms' => '@mms.uscc.net',
        ],
        'cricket' => [
            'name' => 'Cricket',
            'sms' => '@sms.cricketwireless.net',
            'mms' => '@mms.cricketwireless.net',
        ],
        'boost' => [
            'name' => 'Boost Mobile',
            'sms' => '@sms.myboostmobile.com',
            'mms' => '@myboostmobile.com',
        ],
        'metropcs' => [
            'name' => 'Metro PCS',
            'sms' => '@mymetropcs.com',
            'mms' => '@mymetropcs.com',
        ],
        'virgin' => [
            'name' => 'Virgin Mobile',
            'sms' => '@vmobl.com',
            'mms' => '@vmpix.com',
        ],
        'google_fi' => [
            'name' => 'Google Fi',
            'sms' => '@msg.fi.google.com',
            'mms' => '@msg.fi.google.com',
        ],
    ],

    /**
     * Default provider to use if none specified
     */
    'default_provider' => 'uscc',

    /**
     * Default to MMS gateway (supports longer messages and images)
     */
    'prefer_mms' => true,
];

