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
