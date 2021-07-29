<?php

use Shalvah\Upgrader\Upgrader;

$userOldConfig = __DIR__ . '/fixtures/old.php';

it("can detect items added to/removed from maps", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/added_removed_map.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)->dryRun();

    expect($changes)->toHaveCount(3);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'map.key_3',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'map.key_1',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'nested.map.and',
        ],
    ], $changes);
});

it("ignores maps marked as dontTouch()", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/added_removed_map.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)
        ->dontTouch('map')->dryRun();

    expect($changes)->toHaveCount(1);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'nested.map.and',
        ],
    ], $changes);
});

it("can detect items added to lists", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/added_to_list.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)->dryRun();

    expect($changes)->toHaveCount(2);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'nested.list',
        ],
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'list',
        ],
    ], $changes);
    expect($changes[0]['value']->value)->toEqual('new_item');
    expect($changes[1]['value']->value)->toEqual('black');
});

it("ignores lists marked as dontTouch()", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/added_to_list.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)
        ->dontTouch('nested.list')->dryRun();

    expect($changes)->toHaveCount(1);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'list',
        ],
    ], $changes);
});

it("handles move()d items properly", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/moved.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)
        ->move('other_thing', 'new_other_thing')->dryRun();

    expect($changes)->toHaveCount(2);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'new_other_thing',
        ],
        [
            'type' => Upgrader::CHANGE_MOVED,
            'key' => 'other_thing',
            'new_key' => 'new_other_thing',
        ],
    ], $changes);
});

it("properly handles class name constants written differently", function () use ($userOldConfig) {
    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/alternate_class_name_reference.php';
    $changes = Upgrader::ofConfigFile($userOldConfig, $sampleNewConfig)->dryRun();

    expect($changes)->toBeEmpty();
});
