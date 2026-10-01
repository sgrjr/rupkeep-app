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
    // AT&T (txt.att.net / mms.att.net) and T-Mobile (tmomail.net, which also
    // carried Sprint) shut their email-to-SMS gateways in 2025 (TASK-053 /
    // TASK-468). An address on one of them cannot be delivered to, so they
    // are no longer offered; a driver on those carriers needs a mailbox or
    // push instead.
    'providers' => [
        'verizon' => [
            'name' => 'Verizon',
            'sms' => '@vtext.com',
            'mms' => '@vzwpix.com',
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

