<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class NameManager
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function replace(MappedResult $mappedResult, array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $parentVisitor = new ParentConnectingVisitor();

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($parentVisitor);
        $traverser->addVisitor(new NameReplacerVisitor($mappedResult));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, Node\Name>>
     */
    public function findCurrentAliases(array $nodes, bool $includeUseImports): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $visitor = new CurrentAliasesVisitor($includeUseImports);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $visitor->getCurrentAliases();
    }

    /**
     * @param Node[] $nodes
     * @return array<int, Node\Name\FullyQualified[]>
     */
    public function findNewDefinitions(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $visitor = new NewDefinitionVisitor();

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $visitor->getNewDefinitions();
    }
}