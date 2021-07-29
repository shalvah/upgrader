<?php

use A\B;
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
        'list' => []
    ],
    'list' => [
        'baa',
        'baa',
        \Exception::class,
        \Illuminate\Support\Collection::class,
    ],
    'thing' => false,
    'other_thing' => rand(0, 1),
];