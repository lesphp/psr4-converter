<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use LesPhp\PSR4Converter\Converter\Node\ExtractMappedUnitVisitor;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Builder\Use_;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;

class ReplaceNameVisitor extends NodeVisitorAbstract
{
    private \SplObjectStorage $typeByName;

    /**
     * @var array<int, array<string, string>>
     */
    private readonly array $convertedNamesMap;

    public function __construct(MappedResult $mappedResult)
    {
        $this->convertedNamesMap = $this->getConvertedNames($mappedResult);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getConvertedNames(MappedResult $mappedResult): array
    {
        $convertedNamesMap = [
            Node\Stmt\Use_::TYPE_NORMAL => [],
            Node\Stmt\Use_::TYPE_FUNCTION => [],
            Node\Stmt\Use_::TYPE_CONSTANT => [],
        ];

        foreach ($mappedResult->getUnits() as $mappedUnit) {
            if ($mappedUnit->isCompound()) {
                $types = array_map(
                    fn (string $componentStmtClass) => $this->getUseTypeByStmtClass($componentStmtClass),
                    $mappedUnit->getComponentStmtClasses()
                );
                $originalFullQualifiedNames = $mappedUnit->getOriginalFullQualifiedName();
                $newFullQualifiedNames = $mappedUnit->getNewFullQualifiedName();
            } else {
                $types = (array)$this->getUseTypeByStmtClass($mappedUnit->getStmtClass());
                $originalFullQualifiedNames = (array)$mappedUnit->getOriginalFullQualifiedName();
                $newFullQualifiedNames = (array)$mappedUnit->getNewFullQualifiedName();
            }

            array_walk(
                $types,
                function ($type, $i) use (&$convertedNamesMap, $newFullQualifiedNames, $originalFullQualifiedNames) {
                    if ($type === Node\Stmt\Use_::TYPE_UNKNOWN) {
                        return;
                    }

                    $convertedNamesMap[$type][$newFullQualifiedNames[$i]] = $originalFullQualifiedNames[$i];
                }
            );
        }

        return $convertedNamesMap;
    }

    private function getUseTypeByStmtClass(string $stmtClass): int
    {
        if (is_a($stmtClass, Node\Stmt\Function_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_FUNCTION;
        } elseif (is_a($stmtClass, Node\Const_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_CONSTANT;
        } elseif (is_a($stmtClass, Node\Stmt\If_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_UNKNOWN;
        } else {
            $type = Node\Stmt\Use_::TYPE_NORMAL;
        }

        return $type;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->typeByName = new \SplObjectStorage();

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\GroupUse) {
            // Convert Node\Stmt\GroupUse to Node\Stmt\Use_[] to facilite names conversions
            return array_map(function (Node\Stmt\UseUse $useUse) use ($node) {
                $newUseBuilder = new Use_(Node\Name::concat($node->prefix, $useUse->name), $node->type);

                return $newUseBuilder->getNode();
            }, $node->uses);
        } elseif ($node instanceof Node\Stmt\UseUse) {
            /** @var Node\Stmt\GroupUse|Node\Stmt\Use_ $parent */
            $parent = $node->getAttribute('parent');
            $namespacedName = $parent instanceof Node\Stmt\GroupUse
                ? Node\Name::concat($parent->prefix, $node->name)
                : $node->name;
            $type = $parent->type !== Node\Stmt\Use_::TYPE_UNKNOWN ? $parent->type : $node->type;

            $this->typeByName[$node->name] = $type;

            if (!in_array((string)$namespacedName, $this->convertedNamesMap[$type], true)) {
                return null;
            }

            $node->name = new Name(
                array_search((string)$namespacedName, $this->convertedNamesMap[$type], true),
                $node->name->getAttributes()
            );

            return $node;
        }

        if (
            $node instanceof Node\Expr\FuncCall
            && $node->name instanceof Name
        ) {
            $this->typeByName[$node->name] = Node\Stmt\Use_::TYPE_FUNCTION;
        } elseif ($node instanceof Node\Expr\ConstFetch) {
            $this->typeByName[$node->name] = Node\Stmt\Use_::TYPE_CONSTANT;
        } elseif ($node instanceof Node\Name && !isset($this->typeByName[$node])) {
            $this->typeByName[$node] = Node\Stmt\Use_::TYPE_NORMAL;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node->getAttribute(ExtractMappedUnitVisitor::CONVERTED_NAME_ATTRIBUTE) !== null) {
            $this->replaceConvertedName($node);

            return $node;
        }

        if (!$node instanceof Node\Name) {
            return null;
        }

        $isFullyQualified = $node->isFullyQualified();
        $isResolvedName = $node->hasAttribute('resolvedName');

        if (isset($this->typeByName[$node]) && ($isFullyQualified || $isResolvedName)) {
            $searchName = $isFullyQualified
                ? $node
                : $node->getAttribute('resolvedName');
            $newName = array_search(
                (string)$searchName,
                $this->convertedNamesMap[$this->typeByName[$node]]
            );

            if ($newName !== false) {
                return new FullyQualified($newName);
            } elseif (!$searchName->isSpecialClassName()) {
                return new FullyQualified($searchName, $searchName->getAttributes());
            }
        }

        return null;
    }

    private function replaceConvertedName(Node $node): void
    {
        $newName = $node->getAttribute(ExtractMappedUnitVisitor::CONVERTED_NAME_ATTRIBUTE);
        if (
            $node instanceof Node\Stmt\ClassLike
            || $node instanceof  Node\Stmt\Function_
            || $node instanceof Node\Const_
        ) {
            $node->name = new Node\Identifier($newName);
        } elseif (
            $node instanceof Node\Stmt\Namespace_
            || $node instanceof Node\Expr\FuncCall
        ) {
            $node->name = new Node\Name($newName);
        }
    }
}
