<?php

namespace LesPhp\PSR4Converter\Converter\Node;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeManager extends NodeVisitorAbstract
{
    /**
     * @param Node[] $nodes
     * @param MappedResult[] $additionalMappedResults
     * @return Node[]
     */
    public function extract(MappedUnit $mappedUnit, MappedResult $mappedResult, array $nodes, bool $createAliases, array $additionalMappedResults = []): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ExtractMappedUnitVisitor($mappedUnit, $mappedResult, $createAliases, $additionalMappedResults));

        return $traverser->traverse($nodes);
    }
}
