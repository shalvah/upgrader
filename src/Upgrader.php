<?php


namespace Knuckles\Scribe\Tools;

use PhpParser;
use PhpParser\{Node, NodeFinder, Lexer, NodeTraverser, NodeVisitor, Parser, ParserFactory, PrettyPrinter};

class Upgrader
{
    public const CHANGE_REMOVED = 'removed';
    public const CHANGE_MOVED = 'moved';
    public const CHANGE_ADDED = 'added';
    public const CHANGE_ARRAY_ITEM_ADDED = 'added_to_array';

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
}