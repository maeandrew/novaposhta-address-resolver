<?php

declare(strict_types=1);

use MaeAndrew\NovaPoshtaAddressResolver\Contracts\LocationProvider;

return [
    'driver' => env('NOVA_POSHTA_RESOLVER_DRIVER', 'custom'),

    'drivers' => [
        'custom' => [
            'provider' => LocationProvider::class,
            'parser' => null,
            'matching_strategy' => null,
            'policy' => [],
            'ai_interpreter' => null,
        ],
    ],

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 86400,
        'prefix' => 'novaposhta-address-resolver',
        'statuses' => ['resolved', 'ambiguous'],
    ],

    'events' => [
        'enabled' => true,
        'dispatch_on_cache' => false,
    ],

    'queue' => [
        'connection' => null,
        'queue' => null,
    ],
];
