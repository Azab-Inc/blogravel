<?php

return [
    'platform_domain' => env('TENANCY_PLATFORM_DOMAIN', 'blogravel.com'),

    'reserved_labels' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANCY_RESERVED_LABELS', 'www,admin,api')),
    ))),
];
