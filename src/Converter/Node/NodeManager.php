<?php

namespace LesPhp\PSR4Converter\Converter\Node;

use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
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
    public function extract(MappedUnit $mappedUnit, MappedResult $mappedResult, array $nodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ExtractMappedUnitVisitor($mappedUnit, $mappedResult));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $currentNodes
     * @return Node[]
     * @throws IncompatibleMergeFilesException
     */
    public function append(array $currentNodes, array $appendNodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new AppendNodesVisitor($appendNodes));

        return $traverser->traverse($currentNodes);
    }
}
