<?php

use App\Models\Entitlement;

return [
    'entitlements' => [
        Entitlement::TYPE_JOB_SEEKER_ACCESS => [
            'amount' => '115.00',
            'currency' => 'JMD',
        ],

        Entitlement::TYPE_EMPLOYER_POSTING_ACCESS => [
            'amount' => '120.00',
            'currency' => 'JMD',
        ],
    ],
];