<?php

namespace LesPhp\PSR4Converter\Converter\Node;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use LesPhp\PSR4Converter\Parser\Node\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;

class ExtractMappedUnitVisitor extends AbstractNodeVisitor
{
    private bool $reachedTargetUnit;

    /**
     * @var Node\Stmt\Declare_[]
     */
    private array $openedDeclares;

    /**
     * @param MappedResult[] $additionalMappedResults
     */
    public function __construct(
        private readonly MappedUnit $mappedUnit,
        private readonly MappedResult $mappedResult,
        private readonly bool $createAliases,
        private readonly array $additionalMappedResults = []
    ) {
        parent::__construct();
    }

    public function before(array $nodes)
    {
        $this->reachedTargetUnit = false;
        $this->openedDeclares = [];

        return $this->nameManager->replaceFullyQualifiedNames($this->mappedResult, $nodes, $this->additionalMappedResults);
    }

    public function enter(Node $node)
    {
        if ($node instanceof Node\Stmt\Declare_ && !$this->reachedTargetUnit) {
            $this->openedDeclares[] = $node;

            return null;
        } elseif ($node instanceof Node\Stmt\Namespace_ && $this->isTargetNamespace($node)) {
            return null;
        } elseif ($this->isValidNamespacedStmt($node) && $this->isTargetUnit($node)) {
            $this->reachedTargetUnit = true;
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    public function leave(Node $node)
    {
        if ($node instanceof Node\Stmt\Nop) {
            return NodeTraverser::REMOVE_NODE;
        } elseif ($node instanceof Node\Stmt\Declare_) {
            $openedDeclare = end($this->openedDeclares);

            if ($openedDeclare === $node) {
                array_pop($this->openedDeclares);

                if ($this->reachedTargetUnit || $openedDeclare->stmts === null) {
                    return null;
                }
            }

            return NodeTraverser::REMOVE_NODE;
        } elseif ($node instanceof Node\Stmt\Namespace_ && !$this->isTargetNamespace($node)) {
            return NodeTraverser::REMOVE_NODE;
        } elseif ($this->isValidNamespacedStmt($node) && !$this->isTargetUnit($node)) {
            return NodeTraverser::REMOVE_NODE;
        }

        if ($node instanceof Node\Stmt\Namespace_ && $this->isTargetNamespace($node)) {
            $newName = $this->mappedUnit->getNewNamespace();

            $node->name = $newName !== null
                ? new Name($newName)
                : null;

            return $node;
        } elseif ($this->isTargetUnit($node)) {
            if (is_string($this->mappedUnit->getNewName())) {
                $node->name = match (true) {
                    $node instanceof Node\Expr\FuncCall => new Node\Name($this->mappedUnit->getNewName(), $node->name->getAttributes()),
                    default => new Node\Identifier($this->mappedUnit->getNewName()),
                };
            }

            if ($this->createAliases) {
                return array_merge([$node], $this->createAliasesForOldName());
            }

            return $node;
        }

        return null;
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

    /**
     * @return Node[]
     */
    private function createAliasesForOldName(): array
    {
        $aliasesCall = [];

        if ($this->mappedUnit->isCompound()) {
            $componentStmtClasses = $this->mappedUnit->getComponentStmtClasses();
            $originalFullQualifiedNames = $this->mappedUnit->getOriginalFullQualifiedName();
            $newFullQualifiedNames = $this->mappedUnit->getNewFullQualifiedName();
        } else {
            $componentStmtClasses = (array)$this->mappedUnit->getStmtClass();
            $originalFullQualifiedNames = (array)$this->mappedUnit->getOriginalFullQualifiedName();
            $newFullQualifiedNames = (array)$this->mappedUnit->getNewFullQualifiedName();
        }

        foreach ($componentStmtClasses as $i => $componentStmtClass) {
            if (!$this->isAllowAlias($componentStmtClass) || $newFullQualifiedNames[$i] === $originalFullQualifiedNames[$i]) {
                continue;
            }

            $newName = new Name\FullyQualified($newFullQualifiedNames[$i]);

            $aliasCall = new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Name('class_alias'),
                    [
                        new Node\Arg(new Node\Expr\ClassConstFetch($newName, 'class')),
                        new Node\Arg(new Node\Scalar\String_($originalFullQualifiedNames[$i])),
                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('false'))),
                    ]
                )
            );

            $aliasesCall[] = $aliasCall;
        }

        return $aliasesCall;
    }

    private function isAllowAlias(string $stmtClass): bool
    {
        return is_a($stmtClass, Node\Stmt\Class_::class, true)
            || is_a($stmtClass, Node\Stmt\Interface_::class, true)
            || is_a($stmtClass, Node\Stmt\Trait_::class, true)
            || is_a($stmtClass, Node\Stmt\Enum_::class, true);
    }
}
