<?php


namespace Shalvah\Upgrader;

use Illuminate\Support\Arr;
use PhpParser;
use PhpParser\{Node,
    Node\Stmt,
    Node\Expr,
    Lexer,
    NodeTraverser,
    NodeVisitor,
    Parser,
    ParserFactory,
    PrettyPrinter};

class Upgrader
{
    use ModifiesAst, ComparesAstNodes;

    public const CHANGE_REMOVED = 'removed';
    public const CHANGE_MOVED = 'moved';
    public const CHANGE_ADDED = 'added';
    public const CHANGE_LIST_ITEM_ADDED = 'added_to_list';

    protected array $changes = [];
    protected array $configFiles = [];
    protected array $movedKeys = [];
    protected array $dontTouchKeys = [];

    /** @var Stmt[] */
    protected ?array $userOldConfigFileAst = [];
    /** @var Stmt[] */
    protected ?array $sampleNewConfigFileAst = [];
    /** @var \PhpParser\Node\Stmt[] */
    protected ?array $originalOldConfigFileAst = [];
    protected array $originalOldConfigFileTokens = [];

    public function __construct(string $userOldConfigRelativePath, string $sampleNewConfigAbsolutePath)
    {
        $this->configFiles['user_old'] = $userOldConfigRelativePath;
        $this->configFiles['sample_new'] = $sampleNewConfigAbsolutePath;
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
        $upgradedConfig = $this->applyChanges();
        $this->writeNewConfigFile($upgradedConfig);
    }

    protected function fetchChanges(): void
    {
        [$userCurrentConfigFile, $sampleNewConfigFile] = $this->parseConfigFiles();

        $userCurrentConfigArray = Arr::first(
            $userCurrentConfigFile, fn(Node $node) => $node instanceof Stmt\Return_
        )->expr->items;
        $sampleNewConfigArray = Arr::first(
            $sampleNewConfigFile, fn(Node $node) => $node instanceof Stmt\Return_
        )->expr->items;
        $this->fetchAddedItems($userCurrentConfigArray, $sampleNewConfigArray);
        $this->fetchRemovedAndMovedItems($userCurrentConfigArray, $sampleNewConfigArray);
    }

    /**
     * @param Expr\ArrayItem[] $userCurrentConfig
     * @param Expr\ArrayItem[] $incomingConfig
     */
    protected function fetchAddedItems(
        array $userCurrentConfig, array $incomingConfig, string $rootKey = ''
    )
    {
        if ($this->arrayIsList($incomingConfig)) {
            // We're dealing with a list of items (numeric array)
            $diff = $this->subtractOtherListFromList($incomingConfig, $userCurrentConfig);
            foreach ($diff as $item) {
                $this->changes[] = [
                    'type' => self::CHANGE_LIST_ITEM_ADDED,
                    'key' => $rootKey,
                    'value' => $item['ast']->value,
                    'description' => "- '{$item['text']}' will be added to `$rootKey`.",
                ];
            }
            return;
        }

        foreach ($incomingConfig as $arrayItem) {
            $key = $arrayItem->key->value;
            $value = $arrayItem->value;

            $fullKey = $this->getFullKey($key, $rootKey);
            if ($this->shouldntTouch($fullKey)) {
                continue;
            }

            // Key is in new, but not in old
            if (!$this->hasItem($userCurrentConfig, $key)) {
                $this->changes[] = [
                    'type' => self::CHANGE_ADDED,
                    'key' => $fullKey,
                    'description' => "- `{$fullKey}` will be added.",
                    'value' => $value,
                ];
            } else {
                if ($this->expressionNodeIsArray($value)) {
                    // Key is in both old and new; recurse into array and compare the inner items
                    $this->fetchAddedItems(
                        $this->getItem($userCurrentConfig, $key)->value->items ?? null, $value->items, $fullKey
                    );
                }
            }

        }
    }

    /**
     * @param Expr\ArrayItem[] $userCurrentConfig
     * @param Expr\ArrayItem[]|null $incomingConfig
     */
    protected function fetchRemovedAndMovedItems(
        array $userCurrentConfig, $incomingConfig, string $rootKey = ''
    )
    {
        if ($this->arrayIsList($incomingConfig)) {
            // A list of items (numeric array)
            // We only add, not remove.
            return;
        }

        // Loop over the old config
        foreach ($userCurrentConfig as $arrayItem) {
            $key = $arrayItem->key->value;
            $value = $arrayItem->value;

            $fullKey = $this->getFullKey($key, $rootKey);

            // Key is in old, but was moved somewhere else in new
            if ($this->wasKeyMoved($fullKey)) {
                $this->changes[] = [
                    'type' => self::CHANGE_MOVED,
                    'key' => $fullKey,
                    'new_key' => $this->movedKeys[$fullKey],
                    'description' => "- `$fullKey` will be moved to `{$this->movedKeys[$fullKey]}`.",
                    'new_value' => $value,
                ];
                continue;
            }

            // Key is in old, but not in new
            if (!$this->hasItem($incomingConfig, $key)) {
                $this->changes[] = [
                    'type' => self::CHANGE_REMOVED,
                    'key' => $fullKey,
                    'description' => "- `$fullKey` will be removed.",
                ];
                continue;
            }

            if (!$this->shouldntTouch($fullKey) && $this->expressionNodeIsArray($value)) {
                // Key is in both old and new; recurse into array and compare the inner items
                $this->fetchRemovedAndMovedItems(
                    $value->items, $this->getItem($userCurrentConfig, $key)->value->items ?? null, $fullKey
                );
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

    public function parseConfigFiles(): array
    {
        $userCurrentConfig = $this->getUserOldConfigFileAsAst();
        $incomingConfig = $this->getSampleNewConfigFileAsAst();

        return [$userCurrentConfig, $incomingConfig];
    }

    protected function getUserOldConfigFileAsAst(): ?array
    {
        if (!empty($this->userOldConfigFileAst)) {
            return $this->userOldConfigFileAst;
        }

        $sourceCode = file_get_contents($this->configFiles['user_old']);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->userOldConfigFileAst = $parser->parse($sourceCode);
        return $this->userOldConfigFileAst;
        // Doing this because we need to preserve the formatting when printing later
        // $lexer = new Lexer\Emulative([
        //     'usedAttributes' => [
        //         'comments',
        //         'startLine', 'endLine',
        //         'startTokenPos', 'endTokenPos',
        //     ],
        // ]);
        // $parser = new Parser\Php7($lexer);
        // $this->originalOldConfigFileAst = $parser->parse($sourceCode);
        // $this->originalOldConfigFileTokens = $lexer->getTokens();
        // $traverser = new NodeTraverser();
        // $traverser->addVisitor(new NodeVisitor\CloningVisitor());
        // $traverser->addVisitor(new NodeVisitor\NameResolver(null, [
        //     'preserveOriginalNames' => true
        // ]));
//
        // $this->userOldConfigFileAst = $traverser->traverse($this->originalOldConfigFileAst);
        return $this->userOldConfigFileAst;
    }

    protected function getSampleNewConfigFileAsAst(): ?array
    {
        if (!empty($this->sampleNewConfigFileAst)) {
            return $this->sampleNewConfigFileAst;
        }

        $sourceCode = file_get_contents($this->configFiles['sample_new']);
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
        $this->sampleNewConfigFileAst = $parser->parse($sourceCode);
        return $this->sampleNewConfigFileAst;
        // $traverser = new NodeTraverser();
        // $traverser->addVisitor(new NodeVisitor\NameResolver(null, [
        //     'preserveOriginalNames' => true
        // ]));
        // return $this->sampleNewConfigFileAst = $traverser->traverse($ast);
    }

    protected function applyChanges(): array
    {
        $userConfigAst = $this->getUserOldConfigFileAsAst();
        $configArray =& Arr::first(
            $userConfigAst, fn(Node $node) => $node instanceof Stmt\Return_
        )->expr->items;

        foreach ($this->changes as $change) {
            switch ($change['type']) {
                case self::CHANGE_ADDED:
                    $this->addKey($configArray, $change['key'], $change['value']);
                    break;
                case self::CHANGE_REMOVED:
                    $this->deleteKey($configArray, $change['key']);
                    break;
                case self::CHANGE_MOVED:
                    // Move old value to new key
                    $this->setValue($configArray, $change['new_key'], $change['new_value']);
                    // Then delete old key
                    $this->deleteKey($configArray, $change['key']);
                    break;
                case self::CHANGE_LIST_ITEM_ADDED:
                    $this->pushItemOntoList($configArray, $change['key'], $change['value']);
                    break;
            }
        }

        return $userConfigAst;
    }

    protected function writeNewConfigFile(array $ast)
    {
        // Print out the changes into the user's config file (saving the old one as a backup)
        $prettyPrinter = new PrettyPrinter\Standard(['shortArraySyntax' => true]);
        // $upgradedConfig = $prettyPrinter->printFormatPreserving($ast, $this->incomingConfigFileOriginalAst, $this->incomingConfigFileOriginalTokens);
        $astAsText = $prettyPrinter->prettyPrintFile($ast);

        $userConfigFile = $this->configFiles['user_old'];
        // rename($userConfigFile, "$userConfigFile.bak");
        file_put_contents($userConfigFile.".new", $astAsText);
    }
}