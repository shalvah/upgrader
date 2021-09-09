<?php

use Illuminate\Support\Collection;
use A\B;
use Shalvah\Upgrader;

return [
    'map' => [
        /**
         * Present item comment
         */
        'key_2' => getenv('2'),
        /**
         * Added item 1 comment
         */
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

    /**
     * Added item 2 comment
     */
    'new_other_thing' => rand(0, 1),
];