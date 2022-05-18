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

    public function __construct(private readonly MappedResult $mappedResult)
    {
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

        if ($node->isSpecialClassName()) {
            return null;
        }

        $isFullyQualified = $node->isFullyQualified();
        $isResolvedName = $node->hasAttribute('resolvedName');
        $convertedNamesMap = $this->mappedResult->getConvertedNamesMap();

        if (isset($this->typeByName[$node]) && ($isFullyQualified || $isResolvedName)) {
            $searchName = $isFullyQualified
                ? $node
                : $node->getAttribute('resolvedName');
            $newName = array_search(
                (string)$searchName,
                $convertedNamesMap[$this->typeByName[$node]]
            );

            if ($newName !== false) {
                return new FullyQualified($newName, $node->getAttributes());
            } else {
                return new FullyQualified($searchName, $node->getAttributes());
            }
        }

        return null;
    }

    /**
     * @param Node\Stmt\UseUse[] $useUses
     */
    private function replaceImportNames(array $useUses, int $type): void
    {
        $convertedNamesMap = $this->mappedResult->getConvertedNamesMap();

        foreach ($useUses as $useUse) {
            $this->typeByName[$useUse->name] = $type;
            $newName = array_search((string)$useUse->name, $convertedNamesMap[$type], true);

            if ($newName !== false) {
                $useUse->name = new Name($newName, $useUse->name->getAttributes());
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
}
