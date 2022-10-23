<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

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
    public static function createAliases(array $nodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new CreateImportsVisitor());

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @param MappedResult[] $additionalMappedResults
     * @return Node[]
     */
    public function replaceFullyQualifiedNames(MappedResult $mappedResult, array $nodes, array $additionalMappedResults = []): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(new ReplaceFullyQualifiedNameVisitor($mappedResult, $additionalMappedResults));

        return $traverser->traverse($nodes);
    }
}
