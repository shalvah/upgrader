<?php

use Shalvah\Upgrader\Upgrader;

it("can detect items added to/removed from maps", function () {
    $userOldConfig = [
        'map' => ['removed' => 1],
        'nested' => ['map' => ['baa' => 'baa', 'black' => 'sheep', 'and' => 'more']]
    ];
    $sampleNewConfig = [
        'map' => ['added' => 1],
        'nested' => ['map' => ['baa' => 'baa', 'black' => 'sheep']]
    ];
    $mockUpgrader = mockUpgraderWithConfigs($userOldConfig, $sampleNewConfig);

    $changes = $mockUpgrader->dryRun();

    expect($changes)->toHaveCount(3);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'map.added',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'map.removed',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'nested.map.and',
        ],
    ], $changes);
});

it("can detect items added to lists", function () {
    $mockUpgrader = mockUpgraderWithConfigs(
        ['list' => ['baa', 'baa', 'black'], 'nested' => ['list' => []]],
        ['list' => ['baa', 'baa', 'black', 'sheep'], 'nested' => ['list' => ['item']]]
    );
    $changes = $mockUpgrader->dryRun();

    expect($changes)->toHaveCount(2);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'list',
        ],
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'nested.list',
        ],
    ], $changes);
    expect($changes[0]['value']->value)->toEqual('sheep');
    expect($changes[1]['value']->value)->toEqual('item');
});

it("ignores items marked as dontTouch()", function () {
    $userOldConfig = [
        'specified_by_user' => ['baa', 'baa', 'black'],
        'map' => ['user_specified' => 'some_value'],
    ];
    $sampleNewConfig = [
        'specified_by_user' => ['baa', 'baa', 'black', 'sheep'],
        'map' => ['just_a_sample' => 'a_value'],
    ];
    $changes = mockUpgraderWithConfigs($userOldConfig, $sampleNewConfig)
        ->dryRun();

    expect($changes)->toHaveCount(3);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'specified_by_user',
        ],
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'map.just_a_sample',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'map.user_specified',
        ],
    ], $changes);
    expect($changes[0]['value']->value)->toEqual('sheep');
    expect($changes[1]['value']->value)->toEqual('a_value');

    $changes = mockUpgraderWithConfigs($userOldConfig, $sampleNewConfig)
        ->dontTouch('specified_by_user', 'map')->dryRun();

    expect($changes)->toBeEmpty();
});

it("reports items marked with move(), only if present in old config", function () {
    $userOldConfig = [
        'old' => [
            'item' => 'value',
            'other_item' => 'other_value'
        ]
    ];
    $sampleNewConfig = [
        'old' => [
            'other_item' => 'other_value'
        ],
        'new_item' => 'default'
    ];
    $changes = mockUpgraderWithConfigs($userOldConfig, $sampleNewConfig)
        ->move('old.item', 'new_item')->dryRun();

    expect($changes)->toHaveCount(2);
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'new_item',
        ],
        [
            'type' => Upgrader::CHANGE_MOVED,
            'key' => 'old.item',
            'new_key' => 'new_item',
        ],
    ], $changes);

    $userOldConfig = [
        'old' => [
            'other_item' => 'other_value'
        ]
    ];
    $changes = mockUpgraderWithConfigs($userOldConfig, $sampleNewConfig)
        ->move('old.item', 'new_item')->dryRun();

    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'new_item',
        ],
    ], $changes);
});
