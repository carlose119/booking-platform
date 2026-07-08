<?php

return [
    'default' => 'usd',

    'supported' => [
        'usd' => [
            'label' => 'US Dollar',
            'symbol' => '$',
            'minor_units' => 2,
            'stripe' => true,
        ],
        'eur' => [
            'label' => 'Euro',
            'symbol' => '€',
            'minor_units' => 2,
            'stripe' => true,
        ],
        'gbp' => [
            'label' => 'British Pound',
            'symbol' => '£',
            'minor_units' => 2,
            'stripe' => true,
        ],
        'cad' => [
            'label' => 'Canadian Dollar',
            'symbol' => 'CA$',
            'minor_units' => 2,
            'stripe' => true,
        ],
        'aud' => [
            'label' => 'Australian Dollar',
            'symbol' => 'A$',
            'minor_units' => 2,
            'stripe' => true,
        ],
    ],
];
