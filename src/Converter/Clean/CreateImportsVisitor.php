<?php

namespace LesPhp\PSR4Converter\Converter\Clean;

use LesPhp\PSR4Converter\Converter\Naming\NameManager;
use LesPhp\PSR4Converter\Parser\CustomNameContext;
use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class CreateImportsVisitor extends NodeVisitorAbstract
{
    /**
     * @var FullyQualified[]
     */
    private array $importedNames;

    /**
     * @var array<int, array<string, FullyQualified>>
     */
    private array $newAliases;

    /**
     * @var array<int, array<string, Node\Name>>
     */
    private array $currentAliases;

    private bool $hasNamespace;

    public function __construct(
        private readonly KeywordManager $keywordHelper,
        private readonly CustomNameContext $nameContext
    ) {
    }

    public function beforeTraverse(array $nodes)
    {
        $nodeFinder = new NodeFinder();
        $this->importedNames = [];
        $this->hasNamespace = $nodeFinder->findFirstInstanceOf($nodes, Node\Stmt\Namespace_::class) !== null;

        $this->resetNameMaps();

        if (!$this->hasNamespace) {
            $this->searchCurrentAliases($nodes);
        }

        return null;
    }

    private function resetNameMaps(): void
    {
        $this->newAliases = $this->currentAliases = [
            Node\Stmt\Use_::TYPE_NORMAL => [],
            Node\Stmt\Use_::TYPE_FUNCTION => [],
            Node\Stmt\Use_::TYPE_CONSTANT => [],
        ];
    }

    /**
     * @param Node[] $nodes
     */
    private function searchCurrentAliases(array $nodes): void
    {
        $nameManager = new NameManager();

        $this->currentAliases = $nameManager->findNamesInUse($nodes, true);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->resetNameMaps();
            $this->searchCurrentAliases([$node]);

            return $node;
        }

        if (
            $node instanceof Node\Expr\FuncCall
            && $node->name instanceof FullyQualified
        ) {
            $node->name = $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_FUNCTION, $node->name);

            return $node;
        } elseif (
            $node instanceof Node\Expr\ConstFetch
            && $node->name instanceof FullyQualified
            && $this->keywordHelper->isSpecialConstants((string)$node->name)
        ) {
            $node->name = new Node\Name($node->name->getLast(), $node->getAttributes());

            $this->importedNames[] = $node->name;

            return $node;
        } elseif (
            $node instanceof Node\Expr\ConstFetch
            && $node->name instanceof FullyQualified
        ) {
            $node->name = $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_CONSTANT, $node->name);

            return $node;
        } elseif (
            $node instanceof FullyQualified
            && !in_array($node, $this->importedNames, true)
        ) {
            // Same namespace
            if ((string)$node->slice(0, -1) === (string)$this->nameContext->getNamespace()) {
                return new Node\Name($node->getLast(), $node->getAttributes());
            }

            return $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_NORMAL, $node);
        }

        return null;
    }

    private function replaceNameWithAlias(int $type, FullyQualified $node): Node\Name
    {
        $existentAlias = $this->getAliasFor($type, $node);

        if ($existentAlias !== null) {
            $node = new Node\Name($existentAlias, $node->getAttributes());
        } elseif (count($node->parts) > 1) {
            $node = new Node\Name($this->generateAliasFor($type, $node), $node->getAttributes());
        }

        $this->importedNames[] = $node;

        return $node;
    }

    private function getAliasFor(int $type, FullyQualified $name): ?string
    {
        $currentNames = array_map(fn (Node\Name $currentName) => (string)$currentName, $this->currentAliases[$type]);

        if (in_array((string)$name, $currentNames, true)) {
            return array_search((string)$name, $currentNames, true);
        }

        $newUseClasses = array_map(fn (Node\Name $newUse) => (string)$newUse, $this->newAliases[$type]);

        if (in_array((string)$name, $newUseClasses, true)) {
            return array_search((string)$name, $newUseClasses, true);
        }

        return null;
    }

    private function generateAliasFor(int $type, FullyQualified $name): string
    {
        $existentAlias = $this->getAliasFor($type, $name);

        if ($existentAlias !== null) {
            return $existentAlias;
        }

        $newNameCounter = 0;
        $tryConcatWithNamespace = false;
        $newName = $name->getLast();

        while (isset($this->currentAliases[$type][$newName]) || isset($this->newAliases[$type][$newName])) {
            if ($type === Node\Stmt\Use_::TYPE_NORMAL && !$tryConcatWithNamespace) {
                $newName = implode('', array_slice($name->parts, -2));

                $tryConcatWithNamespace = true;
            } else {
                $newName = $name->getLast().++$newNameCounter;
            }
        }

        $this->newAliases[$type][$newName] = $name;
        $this->nameContext->addAlias($name, $newName, $type);

        return $newName;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $node->stmts = $this->injectNewUses($node->stmts);

            return $node;
        }

        return null;
    }

    /**
     * @param Node[] $stmts
     * @return Node[]
     */
    private function injectNewUses(array $stmts): array
    {
        $newUses = [];

        foreach ($this->newAliases as $type => $newTypedUses) {
            $newUseStmts = array_map(
                function (string $newAlias, Node\Name $name) use ($type) {
                    $newUseUse = new Node\Stmt\UseUse(
                        new Node\Name($name->toString()),
                        $newAlias === $name->getLast() ? null : $newAlias
                    );

                    return new Node\Stmt\Use_(
                        [$newUseUse],
                        $type
                    );
                },
                array_keys($this->newAliases[$type]),
                array_values($this->newAliases[$type])
            );

            $newUses = array_merge($newUses, $newUseStmts);
        }

        $i = 0;
        foreach ($stmts as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\Declare_ && count($stmt->stmts) === 0) {
                continue;
            }

            break;
        }

        array_splice($stmts, $i, 0, $newUses);

        return $stmts;
    }

    public function afterTraverse(array $nodes)
    {
        if (!$this->hasNamespace) {
            return $this->injectNewUses($nodes);
        }

        return null;
    }
}
