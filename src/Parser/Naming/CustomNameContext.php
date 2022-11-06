<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use PhpParser\ErrorHandler;
use PhpParser\NameContext as PhpParserNameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

class CustomNameContext extends PhpParserNameContext
{
    /**
     * @var array<int, string[]>
     */
    protected $definitions = [];

    /**
     * @var array<int, array<string, Name\FullyQualified>>
     */
    protected $references = [];

    /**
     * @var array<int, string[]>
     */
    protected array $addedAlias = [
        Use_::TYPE_NORMAL   => [],
        Use_::TYPE_FUNCTION => [],
        Use_::TYPE_CONSTANT => [],
    ];

    /**
     * @var array<int, string[]>
     */
    protected array $removedAlias = [
        Use_::TYPE_NORMAL   => [],
        Use_::TYPE_FUNCTION => [],
        Use_::TYPE_CONSTANT => [],
    ];

    protected bool $enabledChangeMonitor = false;

    public function __construct(
        ErrorHandler $errorHandler,
        private readonly NameHelper $nameHelper
    )
    {
        parent::__construct($errorHandler);
    }

    public function getAliasForName(Name\FullyQualified $name, int $type): ?string
    {
        if (
            $this->namespace?->toLowerString() === $name->slice(0, -1)?->toLowerString()
            && $this->definitionExists($name->getLast(), $type)
        ) {
            return $name->getLast();
        }

        if (isset($this->origAliases[$type])) {
            foreach ($this->origAliases[$type] as $alias => $fullName) {
                if ($this->nameHelper->lookupNameByType($name->toString(), $type) === $this->nameHelper->lookupNameByType($fullName->toString(), $type)) {
                    return $alias;
                }
            }
        }

        return null;
    }

    public function generateAliasForName(Name\FullyQualified $name, int $type): string
    {
        $newAliasDefault = $newAlias = $name->getLast();
        $tryConcatWithNamespace = false;
        $newNameCounter = 0;

        while (!$this->canUseAlias($newAlias, $type)) {
            if ($type === Use_::TYPE_NORMAL && !$tryConcatWithNamespace) {
                $newAlias = implode('', array_slice($name->parts, -2));

                $tryConcatWithNamespace = true;
            } else {
                $newAlias = $newAliasDefault . ++$newNameCounter;
            }
        }

        return $newAlias;
    }

    public function addAlias(Name $name, string $aliasName, int $type, array $errorAttrs = [])
    {
        parent::addAlias($name, $aliasName, $type, $errorAttrs);

        if ($this->enabledChangeMonitor) {
            $lookupName = $this->getAliasLookupName($aliasName, $type);

            $this->addedAlias[$type][] = $aliasName;

            foreach ($this->removedAlias[$type] as $i => $removedAlias) {
                if ($this->getAliasLookupName($removedAlias, $type) === $lookupName) {
                    unset($this->removedAlias[$type][$i]);
                    break;
                }
            }
        }
    }

    public function updateAlias(string $oldAlias, int $type, string $newAlias, Name $newName): void
    {
        $oldLookupName = $this->getAliasLookupName($oldAlias, $type);

        if (!isset($this->aliases[$type][$oldLookupName])) {
            return;
        }

        foreach ($this->origAliases[$type] as $origAlias => $origName) {
            if (
                ($type === Use_::TYPE_CONSTANT && $oldAlias === $origAlias)
                || ($type !== Use_::TYPE_CONSTANT && strtolower($origAlias) === strtolower($oldAlias))
            ) {
                unset($this->origAliases[$type][$origAlias]);
                unset($this->aliases[$type][$this->getAliasLookupName($origAlias, $type)]);

                break;
            }
        }

        // Only add new alias, without changes monitoring
        parent::addAlias($newName, $newAlias, $type);
    }

    public function getNameForAlias(string $alias, int $type): ?Name
    {
        if (!$this->aliasExists($alias, $type)) {
            return null;
        }

        $lookupName = $this->getAliasLookupName($alias, $type);

        return $this->aliases[$type][$lookupName];
    }

    /**
     * @return array<int, string[]>
     */
    public function getAddedAlias(): array
    {
        return $this->addedAlias;
    }

    /**
     * @return array<int, string[]>
     */
    public function getRemovedAlias(): array
    {
        return $this->removedAlias;
    }

    /**
     * @return array<int, string[]>
     */
    public function aliasIsRemoved(string $alias, int $type): bool
    {
        if (!isset($this->removedAlias[$type])) {
            return false;
        }

        foreach ($this->removedAlias[$type] as $removedAlias) {
            if ($this->getAliasLookupName($alias, $type) === $this->getAliasLookupName($removedAlias, $type)) {
                return true;
            }
        }

        return false;
    }

    public function aliasExists(string $alias, int $type): bool
    {
        return isset($this->aliases[$type][$this->getAliasLookupName($alias, $type)]);
    }

    public function aliasIsUsed(string $alias, int $type): bool
    {
        if (!$this->aliasExists($alias, $type)) {
            return false;
        }

        return $this->referenceExists($alias, $type);
    }

    public function canUseAlias(string $alias, int $type): bool
    {
        return !$this->aliasExists($alias, $type) && !$this->definitionExists($alias, $type) && !$this->referenceExists($alias, $type);
    }

    /**
     * @inheritDoc
     */
    public function startNamespace(Name $namespace = null) {
        parent::startNamespace($namespace);

        $this->definitions = $this->references = [
            Use_::TYPE_NORMAL   => [],
            Use_::TYPE_FUNCTION => [],
            Use_::TYPE_CONSTANT => [],
        ];
    }

    public function addDefinition(string $name, int $type)
    {
        if (isset($this->definitions[$type])) {
            $this->definitions[$type][] = $name;
        }
    }

    public function definitionExists(string $name, int $type): bool
    {
        if (!isset($this->definitions[$type])) {
            return false;
        }

        foreach ($this->definitions[$type] as $definition) {
            if ($this->getAliasLookupName($name, $type) === $this->getAliasLookupName($definition, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getDefinitions(int $type): array
    {
        if (!isset($this->definitions[$type])) {
            return [];
        }

        return $this->definitions[$type];
    }

    public function addReference(string $name, Name\FullyQualified $fullyQualified, int $type)
    {
        if (isset($this->references[$type])) {
            $this->references[$type][$this->getAliasLookupName($name, $type)][] = $fullyQualified;
        }
    }

    public function referenceExists(string $name, int $type): bool
    {
        return isset($this->references[$type][$this->getAliasLookupName($name, $type)]);
    }

    public function referenceExistsForName(string $name, Name\FullyQualified $fullyQualified, int $type): bool
    {
        if (!$this->referenceExists($name, $type)) {
            return false;
        }

        /** @var Name\FullyQualified $referencedName */
        foreach ($this->references[$type][$this->getAliasLookupName($name, $type)] as $referencedName) {
            if ($this->nameHelper->lookupNameByType($fullyQualified->toString(), $type) === $this->nameHelper->lookupNameByType($referencedName->toString(), $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    public function getReferences(int $type): array
    {
        if (!isset($this->references[$type])) {
            return [];
        }

        return array_keys($this->references[$type]);
    }

    public function enableChangeMonitor()
    {
        $this->enabledChangeMonitor = true;
    }

    public function getAliasLookupName(string $aliasName, int $type): string
    {
        // Constant names are case sensitive, everything else case insensitive
        if ($type === Use_::TYPE_CONSTANT) {
            return $aliasName;
        } else {
            return strtolower($aliasName);
        }
    }
}
