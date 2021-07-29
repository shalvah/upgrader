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
        'list' => []
    ],
    'list' => [
        'baa',
        'baa',
        \Exception::class,
        Collection::class,
    ],
    'thing' => false,
    'new_other_thing' => 'default', // Replaces `other_thing`
];