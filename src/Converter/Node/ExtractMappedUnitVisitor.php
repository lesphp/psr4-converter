<?php

namespace LesPhp\PSR4Converter\Converter\Node;

use LesPhp\PSR4Converter\Converter\Naming\NameManager;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Builder;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class ExtractMappedUnitVisitor extends NodeVisitorAbstract
{
    private bool $hasDeclaredNamespace;

    private bool $reachedTargetUnit;

    /**
     * @var Node\Stmt\Declare_[]
     */
    private array $declareNodes;

    public function __construct(
        private readonly MappedUnit $mappedUnit,
        private readonly MappedResult $mappedResult,
        private readonly bool $createAliases,
        private readonly KeywordManager $keywordHelper
    ) {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->hasDeclaredNamespace = false;
        $this->reachedTargetUnit = false;
        $this->declareNodes = [];

        $nameManager = new NameManager();

        return $nameManager->replaceFullyQualifiedNames($this->mappedResult, $nodes, $this->keywordHelper);
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Declare_ && !$this->reachedTargetUnit) {
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
            $newName = $this->mappedUnit->getNewNamespace();

            $node->name = $newName !== null
                ? new Name($newName, $node->name !== null ? $node->name->getAttributes() : [])
                : null;

            return $node;
        } elseif ($this->isTargetUnit($node) && is_string($this->mappedUnit->getNewName())) {
            $newName = match (true) {
                $node instanceof Node\Stmt\ClassLike,
                $node instanceof  Node\Stmt\Function_,
                $node instanceof Node\Const_ => new Node\Identifier($this->mappedUnit->getNewName(), $node->name->getAttributes()),
                $node instanceof Node\Expr\FuncCall => new Node\Name($this->mappedUnit->getNewName(), $node->name->getAttributes())
            };

            $node->name = $newName;

            return $node;
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        if (!$this->hasDeclaredNamespace) {
            $newNamespace = (new Builder\Namespace_($this->mappedUnit->getNewNamespace()))->getNode();

            $nodes = $this->injectIntoNamespace($nodes, $newNamespace);
        }

        $nodes = $this->refactorDeclares($nodes);

        $nodes = $this->createAliasesForOldName($nodes);

        return $nodes;
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
        $docs = [];

        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Declare_) {
                $doc = $node->getDocComment();

                if ($doc !== null) {
                    $docs[] = $doc;
                }

                foreach ($node->declares as $declare) {
                    $declares[(string)$declare->key] = $declare;
                }

                continue;
            }

            $newNodes[] = $node;
        }

        if (count($declares) > 0) {
            $newDeclare = new Node\Stmt\Declare_(
                array_values($declares),
                null,
                ['comments' => $docs]
            );

            array_unshift($newNodes, $newDeclare);
        }

        return $newNodes;
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function createAliasesForOldName(array $nodes): array
    {
        $aliasCall = new Node\Stmt\Expression(
            new Node\Expr\FuncCall(
                new Node\Name('class_alias'),
                [
                    new Node\Arg(new Node\Scalar\String_($this->mappedUnit->getNewFullQualifiedName())),
                    new Node\Arg(new Node\Scalar\String_($this->mappedUnit->getOriginalFullQualifiedName())),
                    new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('false'))),
                ]
            )
        );

        $nodes[] = $aliasCall;

        return $nodes;
    }
}
