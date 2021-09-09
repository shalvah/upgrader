<?php

use A\B;
use Shalvah\Upgrader;

return [
    'map' => [
        /**
         * Removed item 1 comment
         */
        'key_1' => 1,

        /**
         * Present item comment
         */
        'key_2' => getenv('2'),
    ],
    'nested' => [
        'map' => [
            'baa' => 'baa',
            'black' => 'sheep',

            /**
             * Removed item 2 comment
             */
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

    /**
     * Removed item 3 comment
     */
    'other_thing' => rand(0, 1),
];