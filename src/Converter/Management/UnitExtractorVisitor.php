<?php

namespace LesPhp\PSR4Converter\Converter\Management;

use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use PhpParser\Builder;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class UnitExtractorVisitor extends NodeVisitorAbstract
{
    const NEW_CONVERTED_NAME = 'newConvertedName';

    private bool $hasDeclaredNamespace;

    private bool $reachedTargetUnit;

    /**
     * @var Node\Stmt\Declare_[]
     */
    private array $declareNodes;

    public function __construct(
        private MappedUnit $mappedUnit
    ) {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->hasDeclaredNamespace = false;
        $this->reachedTargetUnit = false;
        $this->declareNodes = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\GroupUse) {
            $newUses = array_map(
                fn(Node\Stmt\UseUse $use) => new Node\Stmt\UseUse(
                    Name::concat($node->prefix, $use->name),
                    $use->alias,
                    $use->type
                ),
                $node->uses
            );

            return new Node\Stmt\Use_($newUses, $node->type);
        } elseif ($node instanceof Node\Stmt\Declare_ && !$this->reachedTargetUnit) {
            $this->declareNodes[] = $node;

            return null;
        } elseif ($node instanceof Node\Stmt\Namespace_) {
            $this->hasDeclaredNamespace = true;

            if ($this->isTargetNamespace($node)) {
                return null;
            }
        } elseif ($this->isValidNamespacedStmt($node) && $this->isTargetUnit($node)) {
            $this->reachedTargetUnit = true;
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    private function isTargetNamespace(Node\Stmt\Namespace_ $node): bool
    {
        return $node->getStartTokenPos() === $this->mappedUnit->getNamespaceStartTokenPos()
            && $node->getEndTokenPos() === $this->mappedUnit->getNamespaceEndTokenPos();
    }

    private function isValidNamespacedStmt(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_
            || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $node instanceof Node\Stmt\If_;
    }

    private function isTargetUnit(Node $node): bool
    {
        return is_a($node, $this->mappedUnit->getStmtClass())
            && $node->getStartTokenPos() === $this->mappedUnit->getStartTokenPos()
            && $node->getEndTokenPos() === $this->mappedUnit->getEndTokenPos();
    }

    public function leaveNode(Node $node)
    {
        if (
            $node instanceof Node\Stmt\Declare_
            && (
                !in_array($node, $this->declareNodes)
                || ($node->stmts !== null && !$this->reachedTargetUnit)
            )
        ) {
            return NodeTraverser::REMOVE_NODE;
        } elseif ($node instanceof Node\Stmt\Namespace_ && !$this->isTargetNamespace($node)) {
            return NodeTraverser::REMOVE_NODE;
        } elseif (
            $node instanceof Node\Stmt\Nop
            || (
                $this->isValidNamespacedStmt($node) && !$this->isTargetUnit($node)
            )
        ) {
            return NodeTraverser::REMOVE_NODE;
        }

        if ($node instanceof Node\Stmt\Namespace_ && $this->isTargetNamespace($node)) {
            $node->setAttribute(self::NEW_CONVERTED_NAME, $this->mappedUnit->getNewNamespace());
        } elseif ($this->isTargetUnit($node) && is_string($this->mappedUnit->getNewName())) {
            /** @var Node\Stmt\Class_|Node\Stmt\Interface_|Node\Stmt\Trait_|Node\Stmt\Enum_|Node\Stmt\Function_|Node\Stmt\Const_ $node */
            $node->setAttribute(self::NEW_CONVERTED_NAME, $this->mappedUnit->getNewName());
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        if (!$this->hasDeclaredNamespace) {
            $newNamespace = (new Builder\Namespace_(null))->getNode();

            $newNamespace->setAttributes([self::NEW_CONVERTED_NAME => $this->mappedUnit->getNewNamespace()]);

            $nodes = $this->injectIntoNamespace($nodes, $newNamespace);
        }

        return $this->refactorDeclares($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function injectIntoNamespace(array $nodes, Node\Stmt\Namespace_ $namespace): array
    {
        $convertedNodes = [];
        $namespacedNodes = [];

        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Declare_) {
                $convertedNodes[] = $node;

                if ($node->stmts !== null) {
                    $newStmts = $this->injectIntoNamespace($node->stmts, $namespace);
                    $node->stmts = null;

                    $convertedNodes = array_merge($convertedNodes, $newStmts);
                }
            } else {
                $namespacedNodes[] = $node;
            }
        }

        if (count($namespacedNodes) > 0) {
            $namespace->stmts = $namespace->stmts !== null ? array_merge(
                $namespace->stmts,
                $namespacedNodes
            ) : $namespacedNodes;

            $convertedNodes[] = $namespace;
        }

        return $convertedNodes;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function refactorDeclares(array $nodes): array
    {
        $newNodes = [];
        $declares = [];

        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Declare_) {
                foreach ($node->declares as $declare) {
                    $declares[(string)$declare->key] = $declare;
                }

                continue;
            }

            $newNodes[] = $node;
        }

        if (count($declares) > 0) {
            array_unshift($newNodes, new Node\Stmt\Declare_(array_values($declares)));
        }

        return $newNodes;
    }
}