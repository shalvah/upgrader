<?php

use Shalvah\Upgrader\Upgrader;

it("can detect items added to/removed from maps", function () {
    $configs = [
        [
            'map' => ['removed' => 1],
            'nested' => ['map' => ['baa' => 'baa', 'black' => 'sheep', 'and' => 'more']]
        ],
        [
            'map' => ['added' => 1],
            'nested' => ['map' => ['baa' => 'baa', 'black' => 'sheep']]
        ],
    ];
    $mockUpgrader = mockUpgraderWithConfigs(...$configs);

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
    $configs = [
        ['list' => ['baa', 'baa', 'black'], 'nested' => ['list' => []]],
        ['list' => ['baa', 'baa', 'black', 'sheep'], 'nested' => ['list' => ['item']]]
    ];
    $mockUpgrader = mockUpgraderWithConfigs(...$configs);

    $changes = $mockUpgrader->dryRun();
    expect($changes)->toHaveCount(2);

    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ARRAY_ITEM_ADDED,
            'key' => 'list',
            'value' => 'sheep',
        ],
        [
            'type' => Upgrader::CHANGE_ARRAY_ITEM_ADDED,
            'key' => 'nested.list',
            'value' => 'item',
        ],
    ], $changes);
});

it("ignores items marked as dontTouch()", function () {
    $configs = [
        [
            'specified_by_user' => ['baa', 'baa', 'black'],
            'map' => [
                'user_specified' => 'some_value',
            ],
        ],
        [
            'specified_by_user' => ['baa', 'baa', 'black', 'sheep'],
            'map' => [
                'just_a_sample' => 'a_value',
            ],
        ],
    ];
    $mockUpgrader = mockUpgraderWithConfigs(...$configs);

    $changes = $mockUpgrader->dryRun();
    expect($changes)->toHaveCount(3);

    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ARRAY_ITEM_ADDED,
            'key' => 'specified_by_user',
            'value' => 'sheep',
        ],
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'map.just_a_sample',
            'value' => 'a_value',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'map.user_specified',
        ],
    ], $changes);

    $mockUpgrader = mockUpgraderWithConfigs(...$configs);
    $mockUpgrader->dontTouch('specified_by_user', 'map');
    $changes = $mockUpgrader->dryRun();
    expect($changes)->toBeEmpty();
});
