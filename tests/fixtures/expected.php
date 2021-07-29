<?php

use Illuminate\Support\Collection;
use A\B;
use Shalvah\Upgrader;

return [
    'map' => [
        'key_2' => getenv('2'),
        'key_3' => 'added',
    ],
    'nested' => [
        'map' => [
            'baa' => 'baa',
            'black' => 'sheep',
        ],
        'list' => []
    ],
    'list' => [
        'baa',
        'baa',
        \Exception::class,
        \Illuminate\Support\Collection::class,
        'black',
    ],
    'thing' => false,
    'new_other_thing' => rand(0, 1),
];