<?php

use A\B;
use Illuminate\Support\Collection;
use Shalvah\Upgrader;

return [
    'map' => [
        'key_1' => 1,
        'key_2' => getenv('2'),
    ],
    'nested' => [
        'map' => [
            'baa' => 'baa',
            'black' => 'sheep',
            'and' => 'more',
        ],
        'list' => [
            'new_item' // Added
        ]
    ],
    'list' => [
        'baa',
        'baa',
        'black', // Added
        // '\Exception' removed, but should be ignored
        Collection::class,
    ],
    'thing' => false,
    'other_thing' => rand(0, 1),
];