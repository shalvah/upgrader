<?php

use Shalvah\Upgrader\Upgrader;

$userOldConfig = __DIR__ . '/fixtures/old.php';

it("works as expected", function () use ($userOldConfig) {
    $configFile = sys_get_temp_dir().'/config.php';
    copy($userOldConfig, $configFile);

    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/all.php';
    Upgrader::ofConfigFile($configFile, $sampleNewConfig)
        ->move('other_thing', 'new_other_thing')
        ->dontTouch('nested.list')
        ->upgrade();

    expect(sys_get_temp_dir().'/config.php.bak')->toBeFile();
    expect(file_get_contents(sys_get_temp_dir().'/config.php.bak'))
        ->toEqual(file_get_contents($userOldConfig));

    $upgradedConfig = file_get_contents($configFile);
    $expectedConfig = file_get_contents(__DIR__ . '/fixtures/expected.php');

    $upgradedConfig = str_replace("\r\n", "\n", $upgradedConfig);
    $expectedConfig = str_replace("\r\n", "\n", $expectedConfig);
    expect($upgradedConfig)->toEqual($expectedConfig);
});

it("works even if dry run is called first", function () use ($userOldConfig) {
    $configFile = sys_get_temp_dir().'/config.php';
    copy($userOldConfig, $configFile);

    $sampleNewConfig = __DIR__ . '/fixtures/new_samples/all.php';
    $upgrader = Upgrader::ofConfigFile($configFile, $sampleNewConfig)
        ->move('other_thing', 'new_other_thing')
        ->dontTouch('nested.list');
    $changes = $upgrader->dryRun();

    expect($changes)->toHaveCount(6);
    /** @var $this \Shalvah\Upgrader\Tests\BaseTest */
    $this->assertArraySubset([
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'map.key_3',
        ],
        [
            'type' => Upgrader::CHANGE_LIST_ITEM_ADDED,
            'key' => 'list',
        ],
        [
            'type' => Upgrader::CHANGE_ADDED,
            'key' => 'new_other_thing',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'map.key_1',
        ],
        [
            'type' => Upgrader::CHANGE_REMOVED,
            'key' => 'nested.map.and',
        ],
        [
            'type' => Upgrader::CHANGE_MOVED,
            'key' => 'other_thing',
            'new_key' => 'new_other_thing',
        ],
    ], $changes);

    $upgrader->upgrade();

    expect(sys_get_temp_dir().'/config.php.bak')->toBeFile();
    expect(file_get_contents(sys_get_temp_dir().'/config.php.bak'))
        ->toEqual(file_get_contents($userOldConfig));

    $upgradedConfig = file_get_contents($configFile);
    $expectedConfig = file_get_contents(__DIR__ . '/fixtures/expected.php');

    $upgradedConfig = str_replace("\r\n", "\n", $upgradedConfig);
    $expectedConfig = str_replace("\r\n", "\n", $expectedConfig);
    expect($upgradedConfig)->toEqual($expectedConfig);
});
