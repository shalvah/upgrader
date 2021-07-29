<?php

use Illuminate\Support\Collection;

return [
    'map' => [
        // `key_1` removed
        'key_2' => getenv('2'),
        'key_3' => 'added', // Added
    ],
    'nested' => [
        'map' => [
            'baa' => 'baa',
            'black' => 'sheep',
            // `and` removed
        ],
        'list' => [
            'new_item_dont_touch' // Added, but should be ignored
        ]
    ],
    'list' => [
        'baa',
        'baa',
        'black',
        \Exception::class,
        Collection::class, // Changed
    ],
    'thing' => false,
    'new_other_thing' => 'default', // Replaces `other_thing`
];