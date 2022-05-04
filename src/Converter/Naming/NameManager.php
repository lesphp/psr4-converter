<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class NameManager
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function replaceFullyQualifiedNames(MappedResult $mappedResult, array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(new ReplaceFullyQualifiedNameVisitor($mappedResult));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, Node\Name>>
     */
    public function findNamesInUse(array $nodes, bool $includeImports): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $visitor = new FindNamesInUseVisitor($includeImports);

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
        $visitor = new FindNewDefinitionsVisitor();

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $visitor->getNewDefinitions();
    }
}
