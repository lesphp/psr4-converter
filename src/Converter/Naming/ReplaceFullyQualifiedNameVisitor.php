<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeVisitorAbstract;

class ReplaceFullyQualifiedNameVisitor extends NodeVisitorAbstract
{
    private \SplObjectStorage $typeByName;

    /**
     * @var Node\Stmt\GroupUse[]
     */
    private array $replacedGroupUses;

    /**
     * @var array<int, array<string, string>>
     */
    private readonly array $convertedNamesMap;

    public function __construct(MappedResult $mappedResult)
    {
        $this->convertedNamesMap = $this->getConvertedNames($mappedResult);
    }

    public function beforeTraverse(array $nodes)
    {
        $this->typeByName = new \SplObjectStorage();
        $this->replacedGroupUses = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\GroupUse && !in_array($node, $this->replacedGroupUses)) {
            $newUseUses = array_map(
                fn (Node\Stmt\UseUse $useUse) => new Node\Stmt\UseUse(
                    Node\Name::concat($node->prefix, $useUse->name),
                    $useUse->alias,
                    $useUse->type
                ),
                $node->uses
            );

            $this->replaceImportNames($newUseUses, $node->type);

            $node->prefix = $this->extractPrefixFromUseUses($newUseUses);
            $node->uses = $newUseUses;

            $this->replacedGroupUses[] = $node;

            return $node;
        } elseif ($node instanceof Node\Stmt\Use_) {
            $this->replaceImportNames($node->uses, $node->type);

            return $node;
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Name) {
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
                return new FullyQualified($searchName);
            }
        }

        return null;
    }

    /**
     * @param Node\Stmt\UseUse[] $useUses
     */
    private function replaceImportNames(array $useUses, int $type): void
    {
        foreach ($useUses as $useUse) {
            $this->typeByName[$useUse->name] = $type;
            $newName = array_search((string)$useUse->name, $this->convertedNamesMap[$type], true);

            if ($newName !== false) {
                $useUse->name = new Name($newName);
            }
        }
    }

    /**
     * @param Node\Stmt\UseUse[] $useUses
     */
    private function extractPrefixFromUseUses(array $useUses): Name
    {
        $prefixParts = [];
        $useUsesParts = array_map(fn (Node\Stmt\UseUse $useUse) => $useUse->name->parts, $useUses);
        $useUsesMaxPrefixLength = min(
            array_map(fn (Node\Stmt\UseUse $useUse) => count($useUse->name->parts) - 1, $useUses)
        );
        $i = 0;

        while (
            $i < $useUsesMaxPrefixLength
            && ($useUsesNamePart = array_column($useUsesParts, $i))
            && count($useUsesNamePart) === count($useUses)
            && count(array_unique($useUsesNamePart)) === 1
        ) {
            $prefixParts[] = $useUsesNamePart[0];

            foreach ($useUses as $useUse) {
                array_shift($useUse->name->parts);
            }

            $i++;
        }

        return new Name($prefixParts);
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
}
