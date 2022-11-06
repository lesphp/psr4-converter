<?php

namespace LesPhp\PSR4Converter\Converter\Node;

use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class NodeManager extends NodeVisitorAbstract
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function extract(MappedUnit $mappedUnit, array $nodes, bool $createAliases): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ExtractMappedUnitVisitor($mappedUnit, $createAliases));

        return $traverser->traverse($nodes);
    }
}
