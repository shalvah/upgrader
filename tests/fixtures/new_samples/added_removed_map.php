<?php

use A\B;
use Illuminate\Support\Collection;
use Shalvah\Upgrader;

return [
    'map' => [
        // 'key_1' removed
        'key_2' => getenv('2'),
        'key_3' => 'added', // Added
    ],
    'nested' => [
        'map' => [
            'baa' => 'baa',
            'black' => 'sheep',
            // 'and' removed
        ],
        'list' => []
    ],
    'list' => [
        'baa',
        'baa',
        \Exception::class,
        Collection::class,
    ],
    'thing' => false,
    'other_thing' => rand(0, 1),
];