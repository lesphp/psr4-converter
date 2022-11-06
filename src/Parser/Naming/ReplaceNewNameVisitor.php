<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor\ReplaceNewNameVisitor as DocReplaceNewNameVisitor;
use LesPhp\PSR4Converter\Parser\Node\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;

class ReplaceNewNameVisitor extends AbstractNodeVisitor
{
    /**
     * @var array<int, array<string, string>>
     */
    private array $convertedNamesMap;

    /**
     * @param MappedResult[] $additionalMappedResults
     */
    public function __construct(
        private readonly MappedResult $mappedResult,
        private readonly array $additionalMappedResults = []
    )
    {
        parent::__construct();

        $this->convertedNamesMap = $this->mappedResult->mergeConvertedNamesMap($this->additionalMappedResults, $this->nameHelper);
    }

    public function enter(Node $node)
    {
        $docNode = $this->parseDocFromNode($node);

        if ($docNode !== null) {
            $traversedPhpDoc = $this->traversePhpDoc(
                $docNode,
                new DocReplaceNewNameVisitor(
                    $this->nameHelper, $this->currentNameContext, $this->mappedResult, $this->additionalMappedResults
                )
            );

            $node->setDocComment($traversedPhpDoc);
        }

        if ($node instanceof Node\Stmt\GroupUse) {
            $newUseUses = array_map(
                fn (Node\Stmt\UseUse $useUse) => new Node\Stmt\UseUse(
                    Node\Name::concat($node->prefix, $useUse->name),
                    $useUse->alias,
                    $useUse->type
                ),
                $node->uses
            );

            foreach ($newUseUses as $useUse) {
                $this->replaceImportName($useUse, $node->type);
            }

            $node->prefix = $this->extractPrefixFromUseUses($newUseUses);
            $node->uses = $newUseUses;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        } elseif ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $useUse) {
                $this->replaceImportName($useUse, $node->type);
            }

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Name && $node->name->isFullyQualified()) {
            $node->name = $this->replaceName($node->name, Node\Stmt\Use_::TYPE_FUNCTION);

            return $node;
        } elseif ($node instanceof Node\Expr\ConstFetch && $node->name->isFullyQualified()) {
            $node->name = $this->replaceName($node->name, Node\Stmt\Use_::TYPE_CONSTANT);

            return $node;
        } elseif (
            $node instanceof Node\Name
            && !$node->isSpecialClassName()
            && $node->isFullyQualified()
        ) {
            return $this->replaceName($node, Node\Stmt\Use_::TYPE_NORMAL);
        }

        return $node;
    }

    private function replaceName(FullyQualified $name, int $type): Name
    {
        $newName = array_search($this->nameHelper->lookupNameByType($name->toString(), $type), $this->convertedNamesMap[$type]);

        if ($newName !== false) {
            return new FullyQualified($newName);
        }

        return $name;
    }

    private function replaceImportName(Node\Stmt\UseUse $useUse, int $useType): void
    {
        $type = $useUse->type | $useType;
        $newName = array_search($this->nameHelper->lookupNameByType($useUse->name->toString(), $type), $this->convertedNamesMap[$type]);
        $hasOldAlias = $useUse->alias !== null;
        $oldImportAlias = $useUse->alias?->toString() ?? $useUse->name->getLast();

        if ($newName !== false) {
            $useUse->name = new Name($newName);

            if ($hasOldAlias || $useUse->name->getLast() === $oldImportAlias) {
                $newAlias = $oldImportAlias;
            } else {
                $newAlias = $this->currentNameContext->generateAliasForName(new FullyQualified($newName), $type);

                $useUse->alias = new Node\Identifier($newAlias);
            }

            $this->currentNameContext->updateAlias($oldImportAlias, $type, $newAlias, $useUse->name);
        } elseif ($this->currentNameContext->definitionExists($oldImportAlias, $type)) {
            $newAlias = $this->currentNameContext->generateAliasForName(new FullyQualified($useUse->name), $type);

            $useUse->alias = new Node\Identifier($newAlias);

            $this->currentNameContext->updateAlias($oldImportAlias, $type, $newAlias, $useUse->name);
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
