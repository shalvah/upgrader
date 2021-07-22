<?php


namespace Shalvah\Upgrader;

use PhpParser;
use PhpParser\{Node, NodeFinder, Lexer, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};

class Upgrader
{
    public const CHANGE_REMOVED = 'removed';
    public const CHANGE_MOVED = 'moved';
    public const CHANGE_ADDED = 'added';
    public const CHANGE_ARRAY_ITEM_ADDED = 'added_to_array';

    private array $changes = [];
    private array $configFiles = [];
    private array $movedKeys = [];
    private array $dontTouchKeys = [];

    public function __construct(string $userOldConfigRelativePath, string $sampleNewConfigAbsolutePath)
    {
        $this->configFiles['user_relative'] = $userOldConfigRelativePath;
        $this->configFiles['package_absolute'] = $sampleNewConfigAbsolutePath;
    }

    public static function ofConfigFile(string $userOldConfigRelativePath, string $sampleNewConfigAbsolutePath): self
    {
        return new self($userOldConfigRelativePath, $sampleNewConfigAbsolutePath);
    }

    public function move(string $oldKey, string $newKey): self
    {
        $this->movedKeys[$oldKey] = $newKey;
        return $this;
    }

    /**
     * "Don't touch" these config items.
     * Useful if they contain arrays with keys specified by the user,
     * or lists with values provided entirely by the user
     */
    public function dontTouch(string ...$keys): self
    {
        $this->dontTouchKeys += $keys;
        return $this;
    }

    public function dryRun(): array
    {
        $this->fetchChanges();

        return $this->changes;
    }

    public function upgrade()
    {
        $this->fetchChanges();
        $this->applyChanges();
    }

    protected function fetchChanges(): void
    {
        [$userCurrentConfig, $incomingSampleConfig] = $this->loadConfigs();
        $this->fetchAddedItems($userCurrentConfig, $incomingSampleConfig);
        $this->fetchRemovedAndRenamedItems($userCurrentConfig, $incomingSampleConfig);
    }

    protected function fetchAddedItems(array $userCurrentConfig, array $incomingConfig, string $rootKey = '')
    {
        if (is_array($incomingConfig)) {
            $arrayKeys = array_keys($incomingConfig);
            if (($arrayKeys[0] ?? null) === 0) {
                // We're dealing with a list of items (numeric array)
                $diff = array_diff($incomingConfig, $userCurrentConfig);
                if (!empty($diff)) {
                    foreach ($diff as $item) {
                        $this->changes[] = [
                            'type' => self::CHANGE_ARRAY_ITEM_ADDED,
                            'key' => $rootKey,
                            'value' => $item,
                            'description' => "- '$item' will be added to `$rootKey`.",
                        ];
                    }
                }
                return;
            }
        }

        foreach ($incomingConfig as $key => $value) {
            $fullKey = $this->getFullKey($key, $rootKey);
            if ($this->shouldntTouch($fullKey)) {
                continue;
            }

            // Key is in new, but not in old
            if (!array_key_exists($key, $userCurrentConfig)) {
                $this->changes[] = [
                    'type' => self::CHANGE_ADDED,
                    'key' => $fullKey,
                    'description' => "- `{$fullKey}` will be added.",
                    'value' => $value,
                ];
            } else {
                if (is_array($value)) {
                    // Key is in both old and new; recurse into array and compare the inner items
                    $this->fetchAddedItems($userCurrentConfig[$key] ?? [], $value, $fullKey);
                }
            }

        }
    }

    protected function fetchRemovedAndRenamedItems(array $userCurrentConfig, $incomingConfig, string $rootKey = '')
    {
        if (is_array($incomingConfig)) {
            $arrayKeys = array_keys($incomingConfig);
            if (($arrayKeys[0] ?? null) === 0) {
                // A list of items (numeric array)
                // We only add, not remove.
                return;
            }
        }

        // Loop over the old config
        foreach ($userCurrentConfig as $key => $value) {
            $fullKey = $this->getFullKey($key, $rootKey);

            // Key is in old, but was moved somewhere else in new
            if ($this->wasKeyMoved($fullKey)) {
                $this->changes[] = [
                    'type' => self::CHANGE_MOVED,
                    'key' => $fullKey,
                    'new_key' => $this->movedKeys[$fullKey],
                    'description' => "- `$fullKey` will be moved to `{$this->movedKeys[$fullKey]}`.",
                ];
                continue;
            }

            // Key is in old, but not in new
            if (!array_key_exists($key, $incomingConfig)) {
                $this->changes[] = [
                    'type' => self::CHANGE_REMOVED,
                    'key' => $fullKey,
                    'description' => "- `$fullKey` will be removed.",
                ];
                continue;
            }

            if (!$this->shouldntTouch($fullKey) && is_array($value)) {
                // Key is in both old and new; recurse into array and compare the inner items
                $this->fetchRemovedAndRenamedItems($value, $incomingConfig[$key] ?? [], $fullKey);
            }
        }
    }

    protected function wasKeyMoved(string $oldKey): bool
    {
        return array_key_exists($oldKey, $this->movedKeys);
    }

    protected function shouldntTouch(string $key): bool
    {
        return in_array($key, $this->dontTouchKeys);
    }

    /**
     * Resolve config item key with dot notation
     */
    private function getFullKey(string $key, string $rootKey = ''): string
    {
        if (empty($rootKey)) {
            return $key;
        }

        return "$rootKey.$key";
    }

    public function loadConfigs(): array
    {
        $userCurrentConfig = require $this->configFiles['user_relative'];
        $incomingConfig = require $this->configFiles['package_absolute'];

        return [$userCurrentConfig, $incomingConfig];
    }

    protected function applyChanges()
    {
        [$userConfig] = $this->loadConfigs();

        foreach ($this->changes as $change) {
            switch ($change['type']) {
                case self::CHANGE_ADDED:
                    data_set($userConfig, $change['key'], $change['value']);
                    break;
                case self::CHANGE_REMOVED:
                    $parts = explode('.', $change['key']);
                    $child = array_pop($parts);
                    $parent = &$userConfig;
                    foreach ($parts as $part) {
                        $parent = &$parent[$part];
                    }
                    unset($parent[$child]);
                    break;
                case self::CHANGE_MOVED:
                    // Move old value in new key
                    data_set($userConfig, $change['new_key'], data_get($userConfig, $change['key']));
                    // Then delete old key
                    $parts = explode('.', $change['key']);
                    $child = array_pop($parts);
                    $parent = &$userConfig;
                    foreach ($parts as $part) {
                        $parent = &$parent[$part];
                    }
                    unset($parent[$child]);
                    break;
                case self::CHANGE_ARRAY_ITEM_ADDED:
                    $items = array_merge(data_get($userConfig, $change['key']), [$change['value']]);
                    data_set($userConfig, $change['key'], $items);
                    break;
            }
        }

        ray($userConfig);
        exit;

        // Finally, print out the changes into the user's config file (saving the old one as a backup)
        $prettyPrinter = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        $upgradedConfig = $prettyPrinter->printFormatPreserving($ast, $this->incomingConfigFileOriginalAst, $this->incomingConfigFileOriginalTokens);

        $userConfigFile = $this->configFiles['user_relative'];
        rename($userConfigFile, "$userConfigFile.bak");
        file_put_contents($userConfigFile, $upgradedConfig);
    }
}